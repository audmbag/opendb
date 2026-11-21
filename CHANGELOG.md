# Changelog

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
