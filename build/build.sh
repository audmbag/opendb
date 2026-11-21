#!/usr/bin/env bash
# Construit opendb pour la production, dans build/dist :
#
#   build/build.sh                    l'application prête à embarquer (build/dist/app), avec la taille de chaque partie
#   build/build.sh binary             puis le binaire du système : Linux par Docker, macOS sur un Mac
#   build/build.sh windows            puis le paquet Windows, assemblé avec FrankenPHP et PHP pour Windows
#   build/build.sh <cible> --reuse-app  sans refaire l'application (la CI la prépare une fois, sous Linux)
#
# Chaque cible dépose sa version dans build/dist/release, avec install.sh, install.ps1 et SHA256SUMS.
#
# Variables : VERSION (sinon le fichier VERSION), PHP_VERSION (8.5), BUILDER (static-builder-gnu ou -musl),
# FRANKENPHP_VERSION (macOS et Windows), KEPT_LOCALES (en|fr).
#
# Le dossier du projet n'est jamais modifié : le serveur de développement peut continuer de tourner.
set -euo pipefail

root=$(cd "$(dirname "$0")/.." && pwd)
dist="$root/build/dist"
cache="$root/build/cache"
stage="$dist/stage"
app="$dist/app"
release="$dist/release"

target=${1:-app}
reuse_app=false
[ "${2:-}" = "--reuse-app" ] && reuse_app=true

# La version : VERSION (le tag de la CI, v0.1.0-beta.1), sinon le fichier VERSION du projet, sans le « v ».
version=${VERSION:-$(cat "$root/VERSION" 2> /dev/null || echo dev)}
version=${version#v}
frankenphp_version=${FRANKENPHP_VERSION:-v1.13.5}
# L'archive Windows de cette version de FrankenPHP, telle que publiée : un autre contenu est refusé.
frankenphp_windows_sha256=${FRANKENPHP_WINDOWS_SHA256:-80ec80de9722e44341c476fe53bc8fad744866fa027f9a4c19bd4c0c554e3631}

# Les extensions qu'utilisent opendb et ses dépendances (composer.lock, Number::format pour intl, les exports pour zip).
extensions=ctype,curl,dom,fileinfo,filter,iconv,intl,mbstring,opcache,openssl,pcntl,pdo,pdo_mysql,pdo_pgsql,pdo_sqlite,posix,session,simplexml,sodium,sqlite3,tokenizer,xml,xmlreader,xmlwriter,zip,zlib
# Sous Windows, les autres sont déjà dans php8ts.dll ; pcntl et posix n'y existent pas.
windows_extensions="curl fileinfo intl mbstring openssl pdo_mysql pdo_pgsql pdo_sqlite sodium sqlite3 zip"
# Opendb n'utilise ni Mercure ni Vulcain, que FrankenPHP ajoute par défaut.
xcaddy_args="--with github.com/dunglas/caddy-cbrotli"

step() { printf '\n\033[1m→ %s\033[0m\n' "$1"; }
sha256() { if command -v sha256sum > /dev/null; then sha256sum "$@"; else shasum -a 256 "$@"; fi; }
size() { du -sh "$1" | sed "s|$dist/||"; }

# Une version publiable : l'archive du dossier, et la liste des sommes de contrôle de tout build/dist/release.
package() {
    local os=$1 arch=$2 folder=$3 name
    printf '%s\n' "$version" > "$folder/VERSION"
    mkdir -p "$release"
    if [ "$os" = windows ]; then
        name="opendb-$os-$arch.zip"
        rm -f "$release/$name"
        (cd "$folder" && zip -qr -9 "$release/$name" .)
    else
        name="opendb-$os-$arch.tar.xz"
        XZ_OPT='-T0 -6' tar -C "$folder" -cJf "$release/$name" .
    fi
    cp "$root/build/install.sh" "$root/build/install.ps1" "$release/"
    (cd "$release" && rm -f SHA256SUMS && sha256 -- opendb-* install.sh install.ps1 > SHA256SUMS)
    size "$release/$name"
    echo "Version $version : publiez le contenu de build/dist/release."
}

build_app() {
    step "Copie des sources"
    # Seulement ce que cette étape refait : les binaires déjà construits restent dans build/dist.
    rm -rf "$stage" "$app"
    mkdir -p "$stage"
    rsync -a \
        --exclude /.git --exclude /node_modules --exclude /vendor --exclude /build/dist --exclude /build/cache \
        --exclude /storage --exclude '/.env' --exclude '/.env.*' --exclude /public/hot --exclude /public/build \
        --exclude '/bootstrap/cache/*.php' --exclude '/database/*.sqlite' --exclude '/lang/php_*.json' \
        "$root/" "$stage/"

    step "Dépendances PHP de production"
    (cd "$stage" && composer install --no-dev --no-interaction --prefer-dist --classmap-authoritative --no-scripts --quiet)

    step "Interface web"
    # Le build de Vite démarre l'application pour Wayfinder : elle le fait comme une copie de développement, avec un
    # .env temporaire, sans toucher au dossier ~/.opendb de la machine.
    mkdir -p "$stage/storage/framework/"{cache/data,sessions,views} "$stage/storage/logs"
    printf 'APP_KEY=base64:%s\nAPP_ENV=production\n' "$(head -c 32 /dev/urandom | base64)" > "$stage/.env"
    (cd "$stage" && bun install --frozen-lockfile --silent && bun run build)

    step "Application à embarquer"
    mkdir -p "$app"
    for path in app bootstrap config database lang public resources routes vendor artisan composer.json LICENSE; do
        cp -R "$stage/$path" "$app/$path"
    done
    cp "$root/build/php.ini" "$app/php.ini"
    printf '%s\n' "$version" > "$app/VERSION"

    # Ce qui ne sert qu'au développement, ou que l'application crée elle-même dans ~/.opendb.
    rm -rf "$app/resources/js" "$app/resources/css" "$app/database/"*.sqlite "$app/lang/"php_*.json
    # Les fichiers publiés par nimbus, un paquet qui n'est plus installé, et Adminer, qu'aucune page n'utilise : servi par
    # opendb serve, il ouvrirait l'administration des bases à quiconque atteint le port.
    rm -rf "$app/public/vendor/nimbus" "$app/public/adminer.php"
    find "$app/bootstrap/cache" -name '*.php' -delete

    # Dans vendor : tests, documentation et fichiers de dépôt des paquets.
    find "$app/vendor" -mindepth 3 -maxdepth 3 -type d \( -iname tests -o -iname test -o -iname docs -o -iname doc -o -name .github \) -prune -exec rm -rf {} +
    find "$app/vendor" -mindepth 3 -maxdepth 3 -type f \( -iname '*.md' -o -name 'phpunit.xml*' -o -name '.gitattributes' -o -name '.editorconfig' -o -name 'phpstan*.neon*' -o -name 'psalm.xml' \) -delete

    # Les langues des données de Faker et des dates de Carbon : celles de l'interface. Une autre locale du système se
    # rabat sur l'anglais, sans erreur. Pour en garder d'autres : KEPT_LOCALES='en|fr|de|es'.
    local kept_locales=${KEPT_LOCALES:-en|fr}
    find "$app/vendor/fakerphp/faker/src/Faker/Provider" -mindepth 1 -maxdepth 1 -type d -regextype posix-extended \
        -regex '.*/[a-z]{2}_[A-Za-z_]+' ! -regex ".*/($kept_locales)_[A-Z]{2}" -exec rm -rf {} +
    find "$app/vendor/nesbot/carbon/src/Carbon/Lang" -type f -regextype posix-extended ! -regex ".*/($kept_locales)(_.*)?\.php" -delete

    # Le classmap ne doit plus mentionner les classes retirées.
    (cd "$app" && composer dump-autoload --no-dev --classmap-authoritative --no-scripts --quiet)

    step "Tailles"
    for part in vendor public/build .; do size "$app/$part"; done
    printf 'archive tar.xz : '
    tar -C "$app" -cf - . | xz -T0 -6 | wc -c | awk '{ printf "%.1f Mo\n", $1 / 1048576 }'
}

build_linux_binary() {
    local arch output
    arch=$(uname -m)
    output="$dist/linux-$arch"
    step "Binaire Linux $arch (FrankenPHP statique : une heure environ la première fois, bien moins ensuite grâce au cache)"
    rm -rf "$output"
    mkdir -p "$output"
    docker build \
        --platform "linux/$(case $arch in x86_64) echo amd64 ;; *) echo arm64 ;; esac)" \
        --build-arg BUILDER="${BUILDER:-static-builder-gnu}" \
        --build-arg PHP_VERSION="${PHP_VERSION:-8.5}" \
        --build-arg PHP_EXTENSIONS="$extensions" \
        --build-arg XCADDY_ARGS="$xcaddy_args" \
        --target export --output "type=local,dest=$output" \
        -f "$root/build/static.Dockerfile" "$app"
    cp "$root/build/launcher/opendb" "$output/opendb"
    chmod +x "$output/opendb" "$output/opendb-core"
    size "$output/opendb-core"
    package linux "$arch" "$output"
}

build_macos_binary() {
    local arch source output
    arch=$(uname -m)
    output="$dist/macos-$arch"
    source="$cache/frankenphp-$frankenphp_version"
    step "Binaire macOS $arch (FrankenPHP $frankenphp_version compilé sur cette machine, avec Homebrew)"
    [ -d "$source" ] || git clone --quiet --depth 1 --branch "$frankenphp_version" https://github.com/php/frankenphp "$source"
    # dist/, dans le dossier de FrankenPHP, garde ses téléchargements et ses bibliothèques d'un build à l'autre.
    # static-php-cli skips FrankenPHP when the binary of the previous build is still there, with the previous
    # application in it: it goes first.
    rm -f "$source/dist/buildroot/bin/frankenphp" "$source/dist/frankenphp-mac-"*
    (cd "$source" && EMBED="$app" PHP_VERSION="${PHP_VERSION:-8.5}" PHP_EXTENSIONS="$extensions" XCADDY_ARGS="$xcaddy_args" ./build-static.sh)
    rm -rf "$output"
    mkdir -p "$output"
    cp "$source/dist/frankenphp-mac-$arch" "$output/opendb-core"
    cp "$root/build/launcher/opendb" "$output/opendb"
    chmod +x "$output/opendb" "$output/opendb-core"
    size "$output/opendb-core"
    package macos "$(case $arch in arm64) echo aarch64 ;; *) echo "$arch" ;; esac)" "$output"
}

build_windows_package() {
    local archive unpacked output extension
    archive="$cache/frankenphp-windows-x86_64-$frankenphp_version.zip"
    unpacked="$cache/frankenphp-windows-$frankenphp_version"
    output="$dist/windows-x86_64"
    step "Paquet Windows (FrankenPHP $frankenphp_version et PHP officiel pour Windows)"
    mkdir -p "$cache"
    [ -f "$archive" ] || curl -fsSL -o "$archive" "https://github.com/php/frankenphp/releases/download/$frankenphp_version/frankenphp-windows-x86_64.zip"
    echo "$frankenphp_windows_sha256  $archive" | sha256 -c - > /dev/null || { rm -f "$archive"; echo "Archive de FrankenPHP inattendue." >&2; exit 1; }
    [ -d "$unpacked" ] || unzip -q "$archive" -d "$unpacked"

    # PHP pour Windows n'a aucun certificat d'autorité : sans eux, aucun appel HTTPS vers un fournisseur d'IA n'aboutit.
    curl -fsSL -o "$cache/cacert.pem" https://curl.se/ca/cacert.pem
    curl -fsSL -o "$cache/cacert.pem.sha256" https://curl.se/ca/cacert.pem.sha256
    (cd "$cache" && sha256 -c cacert.pem.sha256 > /dev/null)

    rm -rf "$output"
    mkdir -p "$output/runtime/ext"
    cp "$unpacked/frankenphp.exe" "$cache/cacert.pem" "$output/runtime/"
    find "$unpacked" -maxdepth 1 -name '*.dll' ! -name 'php8apache*' ! -name 'php8phpdbg*' -exec cp {} "$output/runtime/" \;
    for extension in $windows_extensions; do
        cp "$unpacked/ext/php_$extension.dll" "$output/runtime/ext/"
    done

    # Le runtime Visual C++ avec lequel PHP a été compilé, lu dans l'en-tête de php8ts.dll : install.ps1 l'installe si
    # Windows en a un plus ancien, avec lequel PHP refuserait de démarrer.
    local header major minor
    header=$(od -An -tu4 -j60 -N4 "$unpacked/php8ts.dll" | tr -d ' ')
    read -r major minor <<< "$(od -An -tu1 -j$((header + 26)) -N2 "$unpacked/php8ts.dll")"
    printf '%s.%s\n' "$major" "$minor" > "$output/runtime/vcruntime.txt"
    {
        # ${OPENDB_RUNTIME} est défini par opendb.cmd : le dossier runtime du paquet, où qu'il soit installé.
        echo 'extension_dir = "${OPENDB_RUNTIME}\ext"'
        for extension in $windows_extensions; do echo "extension = $extension"; done
        echo 'curl.cainfo = "${OPENDB_RUNTIME}\cacert.pem"'
        echo 'openssl.cafile = "${OPENDB_RUNTIME}\cacert.pem"'
        cat "$root/build/php.ini"
    } > "$output/runtime/php.ini"

    cp -R "$app" "$output/app"
    rm -f "$output/app/php.ini"
    # cmd lit mal les fichiers aux fins de ligne Unix.
    sed 's/$/\r/' "$root/build/windows/opendb.cmd" > "$output/opendb.cmd"
    cp "$root/build/install.ps1" "$output/install.ps1"
    size "$output/runtime"
    package windows x86_64 "$output"
}

if [ "$reuse_app" = false ]; then
    build_app
elif [ ! -d "$app" ]; then
    echo "build/dist/app est absent : lancez d'abord build/build.sh." >&2
    exit 1
fi

case $target in
    app) ;;
    binary)
        case $(uname -s) in
            Linux) build_linux_binary ;;
            Darwin) build_macos_binary ;;
            *) echo "Le binaire se construit sous Linux ou macOS ; pour Windows : build/build.sh windows." >&2; exit 1 ;;
        esac
        ;;
    windows) build_windows_package ;;
    *) echo "Cible inconnue : $target (app, binary ou windows)." >&2; exit 1 ;;
esac
