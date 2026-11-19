#!/bin/sh
# Installe opendb pour l'utilisateur courant, sans sudo, sur Linux et macOS :
#
#   curl -fsSL https://<site>/install.sh | sh
#
# Chaque version est gardée dans ~/.local/share/opendb/versions/<version>, et ~/.local/bin/opendb mène à celle en
# cours : passer à une autre version ne coupe jamais une commande lancée, et la précédente reste pour revenir en
# arrière (opendb self-update --rollback). Les plus anciennes sont retirées.
#
# Variables : OPENDB_VERSION (une version précise plutôt que la dernière), OPENDB_RELEASES_URL (d'où télécharger),
# OPENDB_INSTALL_DIR (~/.local/share/opendb), OPENDB_BIN_DIR (~/.local/bin), OPENDB_ARCHIVE (une archive déjà
# téléchargée, pour essayer une version ; SHA256SUMS est vérifié s'il se trouve à côté).
set -eu

releases=${OPENDB_RELEASES_URL:-https://github.com/audmbag/opendb/releases}
install_dir=${OPENDB_INSTALL_DIR:-$HOME/.local/share/opendb}
bin_dir=${OPENDB_BIN_DIR:-$HOME/.local/bin}

say() { printf '%s\n' "$*"; }
fail() { printf 'opendb : %s\n' "$*" >&2; exit 1; }

# Le système et le processeur, nommés comme les archives publiées.
case $(uname -s) in
    Linux) os=linux ;;
    Darwin) os=macos ;;
    *) fail "système non pris en charge : $(uname -s). Sous Windows, utilisez install.ps1." ;;
esac
case $(uname -m) in
    x86_64 | amd64) arch=x86_64 ;;
    aarch64 | arm64) arch=aarch64 ;;
    *) fail "processeur non pris en charge : $(uname -m)." ;;
esac
archive="opendb-$os-$arch.tar.xz"

if [ -n "${OPENDB_VERSION:-}" ]; then
    base="$releases/download/$OPENDB_VERSION"
else
    base="$releases/latest/download"
fi

download() {
    if command -v curl > /dev/null 2>&1; then
        curl -fsSL --retry 3 -o "$2" "$1"
    elif command -v wget > /dev/null 2>&1; then
        wget -q -O "$2" "$1"
    else
        fail "curl ou wget est nécessaire."
    fi
}

sha256() {
    if command -v sha256sum > /dev/null 2>&1; then
        sha256sum "$1" | cut -d ' ' -f 1
    else
        shasum -a 256 "$1" | cut -d ' ' -f 1
    fi
}

command -v tar > /dev/null 2>&1 || fail "tar est nécessaire."

work=$(mktemp -d)
trap 'rm -rf "$work"' EXIT INT TERM

if [ -n "${OPENDB_ARCHIVE:-}" ]; then
    cp "$OPENDB_ARCHIVE" "$work/$archive"
    sums="$(dirname "$OPENDB_ARCHIVE")/SHA256SUMS"
    [ -f "$sums" ] && cp "$sums" "$work/SHA256SUMS"
else
    say "Téléchargement de $archive…"
    download "$base/$archive" "$work/$archive" || fail "impossible de télécharger $base/$archive"
    download "$base/SHA256SUMS" "$work/SHA256SUMS" || fail "impossible de télécharger la liste des sommes de contrôle"
fi

if [ -f "$work/SHA256SUMS" ]; then
    expected=$(grep " \*\{0,1\}$archive\$" "$work/SHA256SUMS" | cut -d ' ' -f 1)
    [ -n "$expected" ] || fail "$archive est absent de SHA256SUMS."
    [ "$(sha256 "$work/$archive")" = "$expected" ] || fail "la somme de contrôle de $archive ne correspond pas : téléchargement corrompu ou modifié."
fi

mkdir -p "$work/unpacked"
xz -dc "$work/$archive" 2> /dev/null | tar -xf - -C "$work/unpacked" 2> /dev/null \
    || tar -xJf "$work/$archive" -C "$work/unpacked" \
    || fail "impossible de décompresser $archive (xz est-il installé ?)."

version=$(cat "$work/unpacked/VERSION")
target="$install_dir/versions/$version"

# La version est d'abord écrite à côté, puis mise en place d'un coup.
mkdir -p "$install_dir/versions" "$bin_dir"
rm -rf "$target.partial"
mv "$work/unpacked" "$target.partial"
rm -rf "$target"
mv "$target.partial" "$target"

previous=$(readlink "$install_dir/current" 2> /dev/null || true)
if [ -n "$previous" ] && [ "$previous" != "$target" ]; then
    ln -sfn "$previous" "$install_dir/previous"
fi
ln -sfn "$target" "$install_dir/current"
ln -sfn "$install_dir/current/opendb" "$bin_dir/opendb"

# Seules la version en cours et la précédente restent.
current_name=$(basename "$target")
previous_name=$(basename "$(readlink "$install_dir/previous" 2> /dev/null || echo none)")
for path in "$install_dir"/versions/*; do
    name=$(basename "$path")
    if [ "$name" != "$current_name" ] && [ "$name" != "$previous_name" ]; then
        rm -rf "$path"
    fi
done

say "opendb $version est installé dans $target."

case ":$PATH:" in
    *":$bin_dir:"*) say "Lancez « opendb serve » pour ouvrir l'interface web, ou « opendb chat » dans le terminal." ;;
    *)
        say "Ajoutez $bin_dir à votre PATH, par exemple dans ~/.profile :"
        say "    export PATH=\"$bin_dir:\$PATH\""
        ;;
esac
