# Changelog

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
