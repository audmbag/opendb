# Changelog

## [0.1.0-beta.6](https://github.com/audmbag/opendb/compare/v0.1.0-beta.5...v0.1.0-beta.6) (2026-11-23)


### Features

* **chat:** title the tab of the chat home and of a conversation ([fb6edbc](https://github.com/audmbag/opendb/commit/fb6edbc1f7e969d4aabe73608d94bc3cd8f803db))


### Bug Fixes

* **build:** give static-php-cli the token of GitHub Actions ([ccac70a](https://github.com/audmbag/opendb/commit/ccac70ac40d4d00e161b761c3d514f066afc4b98))
* **build:** keep the tag of FrankenPHP when fetching its sources ([b8c0727](https://github.com/audmbag/opendb/commit/b8c07277b7aafbb364fe94f79b200274b94e4c5f))
* **build:** name the macOS archive without a case in a command substitution ([f12fe37](https://github.com/audmbag/opendb/commit/f12fe37e8104d4d90dab4b40eaccd13ebb55cd16))
* **build:** silence nm for static-php-cli and fetch FrankenPHP next to a restored cache ([fc88dfb](https://github.com/audmbag/opendb/commit/fc88dfbc3565f582503cacfdfe57709314c74f7f))
* **chat:** send the message of the home page once the conversation is mounted for good ([ea3df32](https://github.com/audmbag/opendb/commit/ea3df32c2ab7b4da22f165fbbfd4e44db037645c))
* **menus:** show the destructive actions of the menus in red, the archiving of a conversation included ([e5b03c8](https://github.com/audmbag/opendb/commit/e5b03c806aa41748192bd5ea5f6a1566edf9afde))
* **release:** keep the headers of Homebrew out of the macOS Intel build ([dd43e92](https://github.com/audmbag/opendb/commit/dd43e929b54806a73df4a6138207f4c5e1d8085b))
* **release:** publish a release only once every job has succeeded ([0aa78c3](https://github.com/audmbag/opendb/commit/0aa78c3183f92805c6f1b0633c59d0e67edf23f5))
* **tables:** keep the header of the export list above its rows ([4a235f0](https://github.com/audmbag/opendb/commit/4a235f0218e2d44e54e633d4fda995ed8e6b0405))
* **toaster:** keep the open dialogs and menus open when a toast is pressed ([9de745f](https://github.com/audmbag/opendb/commit/9de745fd5215c03a3243d2ae6fea2d31354a4868))


### Performance Improvements

* **build:** read the classes of a first page from a few bundled files ([8b4bdd4](https://github.com/audmbag/opendb/commit/8b4bdd477c1e5dd5462bfbb44c9f597eb4b50fea))

## [0.1.0-beta.5](https://github.com/audmbag/opendb/compare/v0.1.0-beta.4...v0.1.0-beta.5) (2026-11-21)


### Bug Fixes

* **release:** make each beta the latest release once its files are online ([0a19275](https://github.com/audmbag/opendb/commit/0a19275a85d127f0dd8839563c84201ad98fe76c))

## [0.1.0-beta.4](https://github.com/audmbag/opendb/compare/v0.1.0-beta.3...v0.1.0-beta.4) (2026-11-21)


### Bug Fixes

* **build:** rebuild FrankenPHP so the binary embeds the current application ([cdddb0f](https://github.com/audmbag/opendb/commit/cdddb0f6ec1adf29ee1759f9f2fd2cbd362cda55))
* **chat:** draw the tables of the terminal without a carriage return on Windows ([60b73e6](https://github.com/audmbag/opendb/commit/60b73e638464805d194e34c269005d9a34d1fa89))
* **cloning:** keep copying the rows that follow a refused one ([8ac400f](https://github.com/audmbag/opendb/commit/8ac400fe22927d3be210c607a8046912cb98a325))
* **cloning:** size the chunks of a sqlite source built without dbstat ([58411b7](https://github.com/audmbag/opendb/commit/58411b77318eea12ad6f1696c0b90e97fec45bea))
* **windows:** remove the OPcache settings the package no longer reads ([e4e119e](https://github.com/audmbag/opendb/commit/e4e119e5c8963d9c0ef9b959a513c4b3cfc7ef7f))


### Performance Improvements

* **files:** load a folder of the picker when it is opened ([2322889](https://github.com/audmbag/opendb/commit/23228895a77658d5ae34de8b93b78f3b0bb461a6))
* **install:** write the database of an installed copy ahead in a log ([affc53a](https://github.com/audmbag/opendb/commit/affc53ad0e42ba22cec24485cac89f2ad6067cef))
* **windows:** stop compiling the scripts to disk ([0bd17fa](https://github.com/audmbag/opendb/commit/0bd17fafc6c46b69e63dd9b45c2a855c2207831d))

## [0.1.0-beta.3](https://github.com/audmbag/opendb/compare/v0.1.0-beta.1...v0.1.0-beta.3) (2026-11-21)

La version 0.1.0-beta.2 n'a pas été publiée : ses changements sont ici.


### Features

* **cloning:** anonymise on demand, suggest a separate target database ([5aa79a9](https://github.com/audmbag/opendb/commit/5aa79a97081192bc176f29b455ccf7214425ce1e))
* **cloning:** live progress, cancellation and elapsed time ([c3f315b](https://github.com/audmbag/opendb/commit/c3f315b8e2ca5554394ce5443e97a4db304cf79d))


### Bug Fixes

* **launcher:** run the artisan of OpenDB, not the one of the current folder ([2f1471b](https://github.com/audmbag/opendb/commit/2f1471b5fc21b83b348d4b1e4f21ca88a75def5d))
* **tables:** import QueryParams from where Wayfinder generates it ([978c324](https://github.com/audmbag/opendb/commit/978c324698631f116f6e48ab0b8d51e1dfa9cb48))
* **ui:** fit small laptop screens and high Windows scaling ([d78f2b0](https://github.com/audmbag/opendb/commit/d78f2b003f2dbebd50529561f4ee43117717c494))
* **windows:** install the Visual C++ runtime PHP needs, resolve the package path, faster unpacking ([ecda265](https://github.com/audmbag/opendb/commit/ecda26580704bfb56f374cd47637a44c7b216e77))
* **windows:** keep caches under the user folder, run commands without the help first ([4f9f715](https://github.com/audmbag/opendb/commit/4f9f715d5f090d21a880af2279ad008ade1c6bb5))


### Performance Improvements

* **cloning:** copy faster and keep the schema, values, keys and defaults ([bad77dc](https://github.com/audmbag/opendb/commit/bad77dc1db734ed03f23809f51de1442c14d32b3))
* **files:** list files without checking each one can be read ([c9522a0](https://github.com/audmbag/opendb/commit/c9522a06256d2dbd140e66504fb5cb25b1be1d84))
