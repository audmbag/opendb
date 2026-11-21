#!/usr/bin/env bash
# Vérifie une version publiable comme le ferait un utilisateur, dans un dossier personnel vide :
#
#   build/smoke-test.sh build/dist/release/opendb-linux-x86_64.tar.xz
#
# install.sh l'installe, puis la console répond, l'interface web s'ouvre et un appel HTTPS aboutit.
set -euo pipefail

archive=$(cd "$(dirname "$1")" && pwd)/$(basename "$1")
root=$(cd "$(dirname "$0")/.." && pwd)
home=$(mktemp -d)
port=${SMOKE_PORT:-8123}
# Without a server yet, "kill 0" would stop the whole process group, the caller included.
trap 'if [ -n "${server:-}" ]; then kill "$server" 2> /dev/null || true; fi; rm -rf "$home"' EXIT

check() { printf '  %-34s' "$1"; }

export HOME="$home"
OPENDB_ARCHIVE="$archive" sh "$root/build/install.sh" > /dev/null
opendb="$home/.local/bin/opendb"

check "installation"
cat "$home/.local/share/opendb/current/VERSION"

check "version (opendb --version)"
# The application embedded in the binary, not only the launcher next to it, must be the one of the package.
"$opendb" --version | grep -Fx "OpenDB $(cat "$home/.local/share/opendb/current/VERSION")" \
    || { echo "the binary embeds another version"; exit 1; }

check "artisan d'un projet ignoré"
mkdir -p "$home/projet"
printf '<?php echo "artisan du projet\\n";' > "$home/projet/artisan"
(cd "$home/projet" && "$opendb" --version) | grep '^OpenDB' || { echo "l'artisan du projet a été lancé"; exit 1; }

check "commandes d'OpenDB (opendb)"
"$opendb" | grep -q 'chat' && echo ok

check "commandes de Laravel fermées"
if "$opendb" migrate:status > /dev/null 2>&1; then
    echo "migrate:status a répondu"
    exit 1
fi
echo ok

check "interface web (/login)"
"$opendb" serve --port "$port" > "$home/serve.log" 2>&1 &
server=$!
for _ in $(seq 1 30); do
    curl -fs -o /dev/null "http://127.0.0.1:$port/login" && break
    sleep 1
done
curl -fs -o /dev/null -w '%{http_code}\n' "http://127.0.0.1:$port/login"

check "appel HTTPS (certificats)"
# Par curl, comme les SDK des fournisseurs d'IA, avec le PHP du binaire et le dossier d'extraction du lanceur.
TMPDIR="$home/.opendb/tmp" "$home/.local/share/opendb/current/opendb-core" php-cli -r '
    $request = curl_init("https://api.github.com/zen");
    curl_setopt_array($request, [CURLOPT_RETURNTRANSFER => true, CURLOPT_USERAGENT => "opendb"]);
    curl_exec($request);
    echo curl_getinfo($request, CURLINFO_HTTP_CODE), " ", curl_error($request), PHP_EOL;
    exit(curl_getinfo($request, CURLINFO_HTTP_CODE) === 200 ? 0 : 1);
'
