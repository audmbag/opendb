<?php

// The autoloader of the application of the tests, as Composer's: one class per file of src/, then its "files", each
// run once.
spl_autoload_register(static function (string $class): void {
    $file = dirname(__DIR__).'/src/'.substr($class, strlen('Fixture\\')).'.php';

    if (str_starts_with($class, 'Fixture\\') && is_file($file)) {
        require $file;
    }
});

foreach (['helpers' => dirname(__DIR__).'/src/helpers.php', 'strict-helpers' => dirname(__DIR__).'/src/strict-helpers.php'] as $identifier => $file) {
    if (empty($GLOBALS['__composer_autoload_files'][$identifier])) {
        $GLOBALS['__composer_autoload_files'][$identifier] = true;
        require $file;
    }
}
