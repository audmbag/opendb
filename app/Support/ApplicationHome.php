<?php

declare(strict_types=1);

namespace App\Support;

use App\Services\SystemInfoService;
use Illuminate\Foundation\Application;

/**
 * Where an installed copy of the application keeps everything it writes: ~/.opendb, the folder of SystemInfoService.
 *
 * An installed copy runs from a folder it must not write to, which a new version replaces: the one the binary unpacks
 * itself into, for instance. Its configuration, its database, its storage and the caches of the framework all go to
 * the folder of the user instead, and stay there from one version to the next. A checkout with its own `.env`, like
 * the one of the developer, and the tests, keep the folders of the project.
 *
 * It is read before the framework starts, so it relies on nothing but PHP and the paths it is given.
 */
final readonly class ApplicationHome
{
    /**
     * The name of the application before its configuration is read: the folder is the one config('app.name') gives.
     */
    public const string NAME = 'OpenDB';

    /**
     * The caches of the framework hold absolute paths: each copy of the application has its own.
     *
     * @var array<string, string>
     */
    private const array BOOTSTRAP_CACHES = [
        'APP_CONFIG_CACHE' => 'config.php',
        'APP_ROUTES_CACHE' => 'routes.php',
        'APP_EVENTS_CACHE' => 'events.php',
        'APP_SERVICES_CACHE' => 'services.php',
        'APP_PACKAGES_CACHE' => 'packages.php',
    ];

    /**
     * The folders of storage Laravel expects to find rather than to create.
     */
    private const array STORAGE_FOLDERS = ['app/private', 'app/public', 'framework/cache/data', 'framework/sessions', 'framework/views', 'logs'];

    private function __construct(
        public string $root,
        public string $basePath,
        private string $separator,
    ) {}

    /**
     * The home of an installed copy, or null for a checkout with its own `.env` and for the tests.
     *
     * @param  string|null  $environment  APP_ENV as the process received it
     */
    public static function detect(string $basePath, ?string $environment, SystemInfoService $system): ?self
    {
        if ($environment === 'testing' || is_file($basePath.DIRECTORY_SEPARATOR.'.env')) {
            return null;
        }

        return new self($system->applicationPath(), $basePath, $system->isWindows() ? '\\' : '/');
    }

    /**
     * Point the application to the folders of the user, created with its key on the first launch.
     */
    public function apply(Application $app): void
    {
        $this->prepare();

        $app->useEnvironmentPath($this->path('config'));
        $app->useStoragePath($this->path('storage'));

        // Laravel takes a cache path starting with neither / nor \ for one inside the application, as C:\Users\… is.
        $app->addAbsoluteCachePathPrefix($this->root);

        // Values the process already received win, as they do over the .env file.
        foreach ($this->environmentVariables() as $name => $value) {
            if (! isset($_SERVER[$name]) && ! isset($_ENV[$name]) && getenv($name) === false) {
                $_SERVER[$name] = $_ENV[$name] = $value;
            }
        }

        $app->instance(self::class, $this);
    }

    /**
     * The variables the configuration reads to find the database and the caches of the framework. Nobody runs a queue
     * worker beside an installed copy: its jobs, like the title of a new conversation, run once the response is sent.
     *
     * @return array<string, string>
     */
    public function environmentVariables(): array
    {
        return [
            'DB_DATABASE' => $this->databasePath(),
            'QUEUE_CONNECTION' => 'deferred',
            ...array_map($this->bootstrapCachePath(...), self::BOOTSTRAP_CACHES),
        ];
    }

    public function databasePath(): string
    {
        return $this->path('database.sqlite');
    }

    public function environmentFile(): string
    {
        return $this->path('config', '.env');
    }

    /**
     * The folder of the caches of this copy of the application.
     */
    public function bootstrapCacheDirectory(): string
    {
        return $this->path('cache', 'bootstrap', $this->release());
    }

    /**
     * What tells this copy apart from the previous one: its folder, and when its dependencies were installed.
     */
    public function release(): string
    {
        $installed = $this->basePath.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'composer'.DIRECTORY_SEPARATOR.'installed.php';

        return substr(sha1((realpath($this->basePath) ?: $this->basePath).'|'.(@filemtime($installed) ?: '')), 0, 16);
    }

    public function path(string ...$segments): string
    {
        return implode($this->separator, [$this->root, ...$segments]);
    }

    private function bootstrapCachePath(string $file): string
    {
        return $this->bootstrapCacheDirectory().$this->separator.$file;
    }

    /**
     * Create the folders and the key on the first launch of a version. Once they exist, two checks are all it costs.
     */
    private function prepare(): void
    {
        if (is_file($this->environmentFile()) && is_dir($this->bootstrapCacheDirectory())) {
            return;
        }

        foreach ([$this->path('config'), $this->bootstrapCacheDirectory(), ...array_map(fn (string $folder): string => $this->path('storage', ...explode('/', $folder)), self::STORAGE_FOLDERS)] as $directory) {
            if (! is_dir($directory)) {
                @mkdir($directory, 0700, true);
            }
        }

        $this->createEnvironmentFile();
    }

    /**
     * The key encrypting the passwords and the API keys is drawn once, on the first launch, and never leaves the
     * folder of the user: two processes starting together share the same one.
     */
    private function createEnvironmentFile(): void
    {
        $lock = fopen($this->path('config', '.env.lock'), 'c');
        flock($lock, LOCK_EX);

        try {
            if (! is_file($this->environmentFile())) {
                file_put_contents($this->environmentFile(), 'APP_KEY=base64:'.base64_encode(random_bytes(32)).PHP_EOL);
                @chmod($this->environmentFile(), 0600);
            }
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
