# syntax=docker/dockerfile:1
# Binaire statique d'opendb pour Linux : FrankenPHP compilé par static-php-cli, avec l'application embarquée.
# Le contexte de construction est l'application préparée par build/build.sh (build/dist/app).
#
# static-builder-gnu : lié à la glibc 2.28 ou plus récente (toutes les distributions depuis 2018), plus rapide.
# static-builder-musl : entièrement statique, pour Alpine et les systèmes sans glibc.
ARG BUILDER=static-builder-gnu
FROM dunglas/frankenphp:${BUILDER} AS build

ARG BUILDER
ARG TARGETARCH
ARG PHP_VERSION=8.5
ARG PHP_EXTENSIONS
# Les modules de Caddy : opendb n'utilise ni Mercure ni Vulcain, que FrankenPHP ajoute par défaut.
ARG XCADDY_ARGS="--with github.com/dunglas/caddy-cbrotli"

# L'application est copiée hors de dist/ : ce dossier, où static-php-cli garde ses téléchargements et les bibliothèques
# qu'il a compilées, est conservé d'un build à l'autre. Changer l'application ne recompile donc plus ICU, OpenSSL, curl…
COPY . /go/src/opendb

WORKDIR /go/src/app/
RUN --mount=type=cache,id=opendb-${BUILDER}-${TARGETARCH},target=/go/src/app/dist \
    EMBED=/go/src/opendb PHP_VERSION=${PHP_VERSION} PHP_EXTENSIONS=${PHP_EXTENSIONS} XCADDY_ARGS="${XCADDY_ARGS}" ./build-static.sh \
    && cp dist/frankenphp-linux-* /go/src/opendb-core

# Seul le binaire sort du build (docker build --target export --output type=local,dest=…).
FROM scratch AS export
COPY --from=build /go/src/opendb-core /opendb-core
