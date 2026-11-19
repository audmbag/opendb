# Installe opendb pour l'utilisateur courant sous Windows, sans droits d'administrateur :
#
#   irm https://<site>/install.ps1 | iex
#
# Chaque version est gardée dans %LOCALAPPDATA%\opendb\versions\<version>, et current mène à celle en cours :
# passer à une autre version ne coupe jamais une commande lancée, et la précédente reste pour revenir en arrière
# (opendb self-update --rollback). Les plus anciennes sont retirées.
#
# Variables : OPENDB_VERSION (une version précise plutôt que la dernière), OPENDB_RELEASES_URL (d'où télécharger),
# OPENDB_INSTALL_DIR (%LOCALAPPDATA%\opendb), OPENDB_ARCHIVE (un zip déjà téléchargé, pour essayer une version),
# OPENDB_ROLLBACK=1 (revenir à la version précédente).
#
# Le fichier est en UTF-8 sans BOM : le site doit le servir en « text/plain; charset=utf-8 », sans quoi Windows
# PowerShell afficherait mal les accents.
#
# Le script est aussi lancé par « irm | iex » : il ne fait jamais exit, qui fermerait la fenêtre de l'utilisateur, et
# ses erreurs sont levées. Tout se passe dans un bloc, pour ne rien laisser dans la session de l'utilisateur.

& {
    $ErrorActionPreference = 'Stop'
    # La barre de progression ralentit beaucoup les téléchargements de Windows PowerShell.
    $ProgressPreference = 'SilentlyContinue'
    [Net.ServicePointManager]::SecurityProtocol = [Net.ServicePointManager]::SecurityProtocol -bor [Net.SecurityProtocolType]::Tls12

    $releases = if ($env:OPENDB_RELEASES_URL) { $env:OPENDB_RELEASES_URL.TrimEnd('/') } else { 'https://github.com/audmbag/opendb/releases' }
    $installDir = if ($env:OPENDB_INSTALL_DIR) { $env:OPENDB_INSTALL_DIR } else { Join-Path $env:LOCALAPPDATA 'opendb' }
    $versionsDir = Join-Path $installDir 'versions'
    $current = Join-Path $installDir 'current'
    $previous = Join-Path $installDir 'previous'
    $binDir = Join-Path $installDir 'bin'
    $archiveName = 'opendb-windows-x86_64.zip'

    function Fail([string] $message) {
        throw "opendb : $message"
    }

    # Le dossier vers lequel mène une jonction, ou $null.
    function Get-JunctionTarget([string] $path) {
        if (-not (Test-Path -LiteralPath $path)) { return $null }
        $target = (Get-Item -LiteralPath $path -Force).Target
        if ($target -is [array]) { $target = $target[0] }
        return $target
    }

    # Une jonction se retire avec rmdir : Remove-Item effacerait aussi le contenu de la version vers laquelle elle mène.
    function Set-Junction([string] $path, [string] $target) {
        if (Test-Path -LiteralPath $path) { cmd /c rmdir "$path" | Out-Null }
        New-Item -ItemType Junction -Path $path -Target $target | Out-Null
    }

    function Install-Opendb([bool] $Rollback) {
        if ($Rollback) {
            $back = Get-JunctionTarget $previous
            if (-not $back -or -not (Test-Path -LiteralPath $back)) { Fail 'aucune version précédente à retrouver.' }
            $now = Get-JunctionTarget $current
            Set-Junction $current $back
            if ($now) { Set-Junction $previous $now }
            Write-Host "opendb revient à la version $(Get-Content -LiteralPath (Join-Path $back 'VERSION'))."
            return
        }

        if ([Environment]::Is64BitOperatingSystem -eq $false) { Fail 'opendb demande une version 64 bits de Windows.' }

        $work = Join-Path ([IO.Path]::GetTempPath()) "opendb-$PID"
        New-Item -ItemType Directory -Force -Path $work | Out-Null

        try {
            $archive = Join-Path $work $archiveName

            if ($env:OPENDB_ARCHIVE) {
                Copy-Item -LiteralPath $env:OPENDB_ARCHIVE -Destination $archive
                $sums = Join-Path (Split-Path -Parent $env:OPENDB_ARCHIVE) 'SHA256SUMS'
            } else {
                $base = if ($env:OPENDB_VERSION) { "$releases/download/$($env:OPENDB_VERSION)" } else { "$releases/latest/download" }
                Write-Host "Téléchargement de $archiveName..."
                try {
                    Invoke-WebRequest -UseBasicParsing -Uri "$base/$archiveName" -OutFile $archive
                    $sums = Join-Path $work 'SHA256SUMS'
                    Invoke-WebRequest -UseBasicParsing -Uri "$base/SHA256SUMS" -OutFile $sums
                } catch {
                    Fail "impossible de télécharger $base/$archiveName ($($_.Exception.Message))"
                }
            }

            if (Test-Path -LiteralPath $sums) {
                $line = Get-Content -LiteralPath $sums | Where-Object { $_ -match "\s\*?$([regex]::Escape($archiveName))$" } | Select-Object -First 1
                if (-not $line) { Fail "$archiveName est absent de SHA256SUMS." }
                $expected = ($line -split '\s+')[0].ToLower()
                $actual = (Get-FileHash -Algorithm SHA256 -LiteralPath $archive).Hash.ToLower()
                if ($actual -ne $expected) { Fail "la somme de contrôle de $archiveName ne correspond pas : téléchargement corrompu ou modifié." }
            } elseif (-not $env:OPENDB_ARCHIVE) {
                Fail 'la liste des sommes de contrôle est introuvable.'
            }

            $unpacked = Join-Path $work 'unpacked'
            Expand-Archive -LiteralPath $archive -DestinationPath $unpacked -Force
            $version = (Get-Content -LiteralPath (Join-Path $unpacked 'VERSION') -First 1).Trim()
            $target = Join-Path $versionsDir $version

            New-Item -ItemType Directory -Force -Path $versionsDir, $binDir | Out-Null
            $now = Get-JunctionTarget $current
            if ($now -and ((Resolve-Path -LiteralPath $now).Path -eq $target)) {
                Write-Host "opendb $version est déjà la version installée."
                return
            }
            if (Test-Path -LiteralPath $target) { Remove-Item -LiteralPath $target -Recurse -Force }
            Move-Item -LiteralPath $unpacked -Destination $target

            Set-Junction $current $target
            if ($now -and (Test-Path -LiteralPath $now)) { Set-Junction $previous $now }

            # La commande opendb, qui reste la même d'une version à l'autre : c'est elle qu'un client MCP lance.
            $shim = "@echo off`r`nset `"OPENDB_BINARY=%~f0`"`r`ncall `"%~dp0..\current\opendb.cmd`" %*`r`n"
            [IO.File]::WriteAllText((Join-Path $binDir 'opendb.cmd'), $shim, [Text.Encoding]::ASCII)

            # Seules la version en cours et la précédente restent.
            $kept = @($target, (Get-JunctionTarget $previous)) | Where-Object { $_ }
            Get-ChildItem -LiteralPath $versionsDir -Directory | Where-Object { $kept -notcontains $_.FullName } | ForEach-Object {
                Remove-Item -LiteralPath $_.FullName -Recurse -Force -ErrorAction SilentlyContinue
            }

            $userPath = [Environment]::GetEnvironmentVariable('Path', 'User')
            if (-not (($userPath -split ';') -contains $binDir)) {
                [Environment]::SetEnvironmentVariable('Path', (@($binDir, $userPath) | Where-Object { $_ }) -join ';', 'User')
                $env:Path = "$binDir;$env:Path"
                Write-Host "$binDir est ajouté à votre PATH : ouvrez un nouveau terminal pour l'utiliser."
            }

            Write-Host "opendb $version est installé dans $target."
            Write-Host "Lancez « opendb serve » pour ouvrir l'interface web, ou « opendb chat » dans le terminal."
        } finally {
            Remove-Item -LiteralPath $work -Recurse -Force -ErrorAction SilentlyContinue
        }
    }

    Install-Opendb -Rollback (($args -contains '-Rollback') -or $env:OPENDB_ROLLBACK -eq '1')
} @args
