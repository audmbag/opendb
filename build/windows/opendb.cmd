@echo off
rem Lanceur d'opendb pour Windows, à la racine du paquet : runtime\ (FrankenPHP et PHP) et app\ (l'application).
rem
rem   opendb serve [--port 8000]       l'interface web, sur 127.0.0.1
rem   opendb self-update [--rollback]  la dernière version, ou retour à la précédente
rem   opendb ^<commande^> ...           les commandes de la console (chat, settings, mcp:serve...)
setlocal

set "OPENDB_ROOT=%~dp0"
set "OPENDB_RUNTIME=%~dp0runtime"
rem Le php.ini du paquet, qui active les extensions de runtime\ext.
set "PHPRC=%OPENDB_RUNTIME%"
rem Ce qu'un client MCP lance : le raccourci de bin\ quand il a lancé ce fichier, sinon ce fichier lui-même.
if not defined OPENDB_BINARY set "OPENDB_BINARY=%~f0"

rem La console compile chaque fichier une seule fois : le cache sur disque d'OPcache sert d'une commande à l'autre.
set "home=%USERPROFILE%\.opendb"
if not exist "%home%\cache\opcache" mkdir "%home%\cache\opcache"
if not exist "%home%\php\opendb.ini" call :opcache_ini
set "PHP_INI_SCAN_DIR=%home%\php"

if /i "%~1"=="serve" goto serve
if /i "%~1"=="self-update" goto selfupdate

if not defined APP_URL set "APP_URL=http://127.0.0.1:8000"
"%OPENDB_RUNTIME%\frankenphp.exe" php-cli "%OPENDB_ROOT%app\artisan" %*
exit /b %errorlevel%

:serve
set "port=8000"
if defined OPENDB_PORT set "port=%OPENDB_PORT%"
if /i "%~2"=="--port" set "port=%~3"
rem Les liens vers l'interface web, écrits par le chat de la console, mènent à ce serveur.
if not defined APP_URL set "APP_URL=http://127.0.0.1:%port%"
"%OPENDB_RUNTIME%\frankenphp.exe" php-server --root "%OPENDB_ROOT%app\public" --listen "127.0.0.1:%port%"
exit /b %errorlevel%

:selfupdate
rem Chaque fois sur une seule ligne : cmd relit ce fichier pendant qu'il s'exécute, et la mise à jour le remplace.
rem Le script est lu en UTF-8 explicitement : Windows PowerShell le décoderait autrement, et ses accents avec.
if /i "%~2"=="--rollback" set "OPENDB_ROLLBACK=1" & powershell -NoProfile -ExecutionPolicy Bypass -Command "iex ([IO.File]::ReadAllText('%OPENDB_ROOT%install.ps1', [Text.Encoding]::UTF8))" & exit /b
if not defined OPENDB_RELEASES_URL set "OPENDB_RELEASES_URL=https://github.com/audmbag/opendb/releases"
powershell -NoProfile -ExecutionPolicy Bypass -Command "$r = Invoke-WebRequest -UseBasicParsing '%OPENDB_RELEASES_URL%/latest/download/install.ps1'; iex ([Text.Encoding]::UTF8.GetString($r.RawContentStream.ToArray()))" & exit /b

:opcache_ini
set "PHP_INI_SCAN_DIR=%home%\php"

if /i "%~1"=="serve" goto serve
if /i "%~1"=="self-update" goto selfupdate

if not defined APP_URL set "APP_URL=http://127.0.0.1:8000"
"%OPENDB_RUNTIME%\frankenphp.exe" php-cli "%OPENDB_ROOT%app\artisan" %*
exit /b %errorlevel%

:serve
set "port=8000"
if defined OPENDB_PORT set "port=%OPENDB_PORT%"
if /i "%~2"=="--port" set "port=%~3"
rem Les liens vers l'interface web, écrits par le chat de la console, mènent à ce serveur.
if not defined APP_URL set "APP_URL=http://127.0.0.1:%port%"
"%OPENDB_RUNTIME%\frankenphp.exe" php-server --root "%OPENDB_ROOT%app\public" --listen "127.0.0.1:%port%"
exit /b %errorlevel%

:selfupdate
rem Chaque fois sur une seule ligne : cmd relit ce fichier pendant qu'il s'exécute, et la mise à jour le remplace.
if /i "%~2"=="--rollback" powershell -NoProfile -ExecutionPolicy Bypass -File "%OPENDB_ROOT%install.ps1" -Rollback & exit /b
if not defined OPENDB_RELEASES_URL set "OPENDB_RELEASES_URL=https://github.com/audmbag/opendb/releases"
powershell -NoProfile -ExecutionPolicy Bypass -Command "irm '%OPENDB_RELEASES_URL%/latest/download/install.ps1' | iex" & exit /b

:opcache_ini
rem Hors d'un bloc entre parenthèses, qu'un dossier comme « C:\Users\Jean (Pro) » refermerait.
if not exist "%home%\php" mkdir "%home%\php"
> "%home%\php\opendb.ini" echo opcache.enable_cli = 1
>> "%home%\php\opendb.ini" echo opcache.file_cache = "%home%\cache\opcache"
exit /b
