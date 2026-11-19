# Installs opendb for the current user on Windows, without administrator rights:
#
#   irm https://<site>/install.ps1 | iex
#
# Each version is kept in %LOCALAPPDATA%\opendb\versions\<version>: switching to another one never cuts a running
# command, and the previous one stays to go back to it (opendb self-update --rollback). Older ones are removed.
#
# Variables: OPENDB_VERSION (a given version rather than the latest), OPENDB_RELEASES_URL (where to download from),
# OPENDB_INSTALL_DIR (%LOCALAPPDATA%\opendb), OPENDB_ARCHIVE (a zip already downloaded, to try a version),
# OPENDB_ROLLBACK=1 (go back to the previous version).
#
# The file holds ASCII only, which Windows PowerShell decodes alike however it reads it: the accents of the messages
# are written \u00e9 and restored when they are shown.
#
# It also runs through "irm | iex": it never calls exit, which would close the window of the user, and throws its
# errors. Everything happens in a block, so that nothing is left in the session of the user.

& {
    $ErrorActionPreference = 'Stop'
    # The progress bar slows the downloads of Windows PowerShell down a lot.
    $ProgressPreference = 'SilentlyContinue'
    [Net.ServicePointManager]::SecurityProtocol = [Net.ServicePointManager]::SecurityProtocol -bor [Net.SecurityProtocolType]::Tls12

    $releases = if ($env:OPENDB_RELEASES_URL) { $env:OPENDB_RELEASES_URL.TrimEnd('/') } else { 'https://github.com/audmbag/opendb/releases' }
    $installDir = if ($env:OPENDB_INSTALL_DIR) { $env:OPENDB_INSTALL_DIR } else { Join-Path $env:LOCALAPPDATA 'opendb' }
    $versionsDir = Join-Path $installDir 'versions'
    $current = Join-Path $installDir 'current'
    $previous = Join-Path $installDir 'previous'
    $binDir = Join-Path $installDir 'bin'
    $archiveName = 'opendb-windows-x86_64.zip'
    $vcRedistUrl = 'https://aka.ms/vs/17/release/vc_redist.x64.exe'

    # A message in French: the template is unescaped, never the values, which may be paths full of backslashes.
    function Say([string] $template) {
        Write-Host ([regex]::Unescape($template) -f $args)
    }

    function Fail([string] $template) {
        throw ('opendb : ' + ([regex]::Unescape($template) -f $args))
    }

    # The folder a junction leads to, or $null.
    function Get-JunctionTarget([string] $path) {
        if (-not (Test-Path -LiteralPath $path)) { return $null }
        $target = (Get-Item -LiteralPath $path -Force).Target
        if ($target -is [array]) { $target = $target[0] }
        return $target
    }

    # A junction is removed with rmdir: Remove-Item would also delete the version it leads to.
    function Set-Junction([string] $path, [string] $target) {
        if (Test-Path -LiteralPath $path) { cmd /c rmdir "$path" | Out-Null }
        New-Item -ItemType Junction -Path $path -Target $target | Out-Null
    }

    # The opendb command of bin\, which stays the same from one version to the next: an MCP client runs it. It calls
    # the version itself, by a path without junction nor "..", which the web server could not resolve. The rest of
    # the line is read with the call: the file can be rewritten while it runs, by an update.
    function Write-Shim([string] $target) {
        $shim = "@echo off`r`nset `"OPENDB_BINARY=%~f0`"`r`ncall `"$target\opendb.cmd`" %* & exit /b`r`n"
        [IO.File]::WriteAllText((Join-Path $binDir 'opendb.cmd'), $shim, [Text.Encoding]::ASCII)
    }

    # PHP for Windows needs the Visual C++ runtime it was built with, or a newer one: with an older one, it does not
    # start at all. The package tells which (runtime\vcruntime.txt), and Microsoft installs it after a confirmation.
    function Assert-VcRuntime([string] $runtime, [string] $work) {
        $requiredFile = Join-Path $runtime 'vcruntime.txt'
        if (-not (Test-Path -LiteralPath $requiredFile)) { return }

        $required = [version] (Get-Content -LiteralPath $requiredFile -First 1).Trim()
        $dll = Join-Path $env:SystemRoot 'System32\vcruntime140.dll'
        $installed = [version] '0.0'
        if (Test-Path -LiteralPath $dll) {
            $info = (Get-Item -LiteralPath $dll).VersionInfo
            $installed = [version] "$($info.FileMajorPart).$($info.FileMinorPart)"
        }
        if ($installed -ge $required) { return }

        Say "PHP demande le runtime Visual C++ {0} ou plus r\u00e9cent, et Windows a le {1}." $required $installed
        Say "Installation du Visual C++ Redistributable de Microsoft : acceptez la demande de Windows."
        $redist = Join-Path $work 'vc_redist.x64.exe'
        Invoke-WebRequest -UseBasicParsing -Uri $vcRedistUrl -OutFile $redist
        try {
            $process = Start-Process -FilePath $redist -ArgumentList '/install', '/passive', '/norestart' -Verb RunAs -Wait -PassThru
        } catch {
            Fail "le runtime Visual C++ n'a pas \u00e9t\u00e9 install\u00e9. Installez-le depuis {0}, puis relancez l'installation." $vcRedistUrl
        }
        # 0: installed, 3010: installed, a restart is pending, 1638: a newer version is already there.
        if (@(0, 3010, 1638) -notcontains $process.ExitCode) {
            Fail "l'installation du runtime Visual C++ a \u00e9chou\u00e9 (code {0}). Installez-le depuis {1}." $process.ExitCode $vcRedistUrl
        }
    }

    function Install-Opendb([bool] $Rollback) {
        if ($Rollback) {
            $back = Get-JunctionTarget $previous
            if (-not $back -or -not (Test-Path -LiteralPath $back)) { Fail "aucune version pr\u00e9c\u00e9dente \u00e0 retrouver." }
            $now = Get-JunctionTarget $current
            Set-Junction $current $back
            if ($now) { Set-Junction $previous $now }
            Write-Shim $back
            Say "opendb revient \u00e0 la version {0}." (Get-Content -LiteralPath (Join-Path $back 'VERSION') -First 1)
            return
        }

        if ([Environment]::Is64BitOperatingSystem -eq $false) { Fail "opendb demande une version 64 bits de Windows." }

        $work = Join-Path ([IO.Path]::GetTempPath()) "opendb-$PID"
        New-Item -ItemType Directory -Force -Path $work | Out-Null

        try {
            $archive = Join-Path $work $archiveName

            if ($env:OPENDB_ARCHIVE) {
                Copy-Item -LiteralPath $env:OPENDB_ARCHIVE -Destination $archive
                $sums = Join-Path (Split-Path -Parent $env:OPENDB_ARCHIVE) 'SHA256SUMS'
            } else {
                $base = if ($env:OPENDB_VERSION) { "$releases/download/$($env:OPENDB_VERSION)" } else { "$releases/latest/download" }
                Say "T\u00e9l\u00e9chargement de {0}..." $archiveName
                try {
                    Invoke-WebRequest -UseBasicParsing -Uri "$base/$archiveName" -OutFile $archive
                    $sums = Join-Path $work 'SHA256SUMS'
                    Invoke-WebRequest -UseBasicParsing -Uri "$base/SHA256SUMS" -OutFile $sums
                } catch {
                    Fail "impossible de t\u00e9l\u00e9charger {0}/{1} ({2})" $base $archiveName $_.Exception.Message
                }
            }

            if (Test-Path -LiteralPath $sums) {
                $line = Get-Content -LiteralPath $sums | Where-Object { $_ -match "\s\*?$([regex]::Escape($archiveName))$" } | Select-Object -First 1
                if (-not $line) { Fail "{0} est absent de SHA256SUMS." $archiveName }
                $expected = ($line -split '\s+')[0].ToLower()
                $actual = (Get-FileHash -Algorithm SHA256 -LiteralPath $archive).Hash.ToLower()
                if ($actual -ne $expected) { Fail "la somme de contr\u00f4le de {0} ne correspond pas : t\u00e9l\u00e9chargement corrompu ou modifi\u00e9." $archiveName }
            } elseif (-not $env:OPENDB_ARCHIVE) {
                Fail "la liste des sommes de contr\u00f4le est introuvable."
            }

            # Expand-Archive of Windows PowerShell takes minutes over thousands of small files: .NET takes seconds.
            Say "D\u00e9compression..."
            $unpacked = Join-Path $work 'unpacked'
            Add-Type -AssemblyName System.IO.Compression.FileSystem
            [IO.Compression.ZipFile]::ExtractToDirectory($archive, $unpacked)

            $version = (Get-Content -LiteralPath (Join-Path $unpacked 'VERSION') -First 1).Trim()
            $target = Join-Path $versionsDir $version

            New-Item -ItemType Directory -Force -Path $versionsDir, $binDir | Out-Null
            $now = Get-JunctionTarget $current
            # The same version again replaces its files, which repairs it: the previous version stays the previous one.
            $reinstall = $now -and ((Resolve-Path -LiteralPath $now).Path -eq $target)
            if ($reinstall) { Say "R\u00e9installation d'opendb {0}..." $version }

            # Before anything changes: without its runtime, the new version could not start.
            Assert-VcRuntime (Join-Path $unpacked 'runtime') $work

            if (Test-Path -LiteralPath $target) { Remove-Item -LiteralPath $target -Recurse -Force }
            Move-Item -LiteralPath $unpacked -Destination $target

            Set-Junction $current $target
            if ($now -and -not $reinstall -and (Test-Path -LiteralPath $now)) { Set-Junction $previous $now }
            Write-Shim $target

            # Only the current version and the previous one are kept.
            $kept = @($target, (Get-JunctionTarget $previous)) | Where-Object { $_ }
            Get-ChildItem -LiteralPath $versionsDir -Directory | Where-Object { $kept -notcontains $_.FullName } | ForEach-Object {
                Remove-Item -LiteralPath $_.FullName -Recurse -Force -ErrorAction SilentlyContinue
            }

            $userPath = [Environment]::GetEnvironmentVariable('Path', 'User')
            if (-not (($userPath -split ';') -contains $binDir)) {
                [Environment]::SetEnvironmentVariable('Path', (@($binDir, $userPath) | Where-Object { $_ }) -join ';', 'User')
                $env:Path = "$binDir;$env:Path"
                Say "{0} est ajout\u00e9 \u00e0 votre PATH : ouvrez un nouveau terminal pour l'utiliser." $binDir
            }

            Say "opendb {0} est install\u00e9 dans {1}." $version $target
            Say "Lancez \u00ab opendb serve \u00bb pour ouvrir l'interface web, ou \u00ab opendb chat \u00bb dans le terminal."
        } finally {
            Remove-Item -LiteralPath $work -Recurse -Force -ErrorAction SilentlyContinue
        }
    }

    Install-Opendb -Rollback (($args -contains '-Rollback') -or $env:OPENDB_ROLLBACK -eq '1')
} @args
