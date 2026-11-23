<?php

declare(strict_types=1);

namespace App\Support\ClassBundle;

use RuntimeException;

/**
 * A file the bundle leaves where it is, read from its own path: the message tells why.
 */
final class RefusedFile extends RuntimeException {}
