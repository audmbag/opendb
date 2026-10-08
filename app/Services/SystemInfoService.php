<?php

namespace App\Services;

use App\Enums\OperatingSystemEnum;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Locale;
use RuntimeException;

class SystemInfoService
{
    /**
     * @param  OperatingSystemEnum|null  $os  Système cible (détecté automatiquement si null)
     * @param  string|null  $appName  Nom utilisé pour les dossiers de l'application (config('app.name') si null)
     * @param  array<string, string>|null  $environment  Variables d'environnement à utiliser (celles du processus si null)
     */
    public function __construct(
        protected ?OperatingSystemEnum $os = null,
        protected ?string $appName = null,
        protected ?array $environment = null,
    ) {}

    public function os(): OperatingSystemEnum
    {
        return $this->os ??= OperatingSystemEnum::current();
    }

    public function isLinux(): bool
    {
        return $this->os() === OperatingSystemEnum::LINUX;
    }

    public function isWindows(): bool
    {
        return $this->os() === OperatingSystemEnum::WINDOWS;
    }

    public function isMac(): bool
    {
        return $this->os() === OperatingSystemEnum::MAC;
    }

    /**
     * Locale de la machine, au format langue_PAYS (fr_FR, en_US), ou la langue seule si le pays est inconnu (fr).
     * Lue dans les variables LC_ALL, LC_MESSAGES puis LANG, sinon via l'extension intl.
     *
     * @param  string  $default  Valeur renvoyée si la locale de la machine ne peut pas être déterminée
     */
    public function locale(string $default = 'en_US'): string
    {
        foreach (['LC_ALL', 'LC_MESSAGES', 'LANG'] as $variable) {
            $locale = $this->normalizeLocale($this->env($variable));
            if ($locale !== null) {
                return $locale;
            }
        }

        if ($this->environment === null && class_exists(Locale::class)) {
            return $this->normalizeLocale(Locale::getDefault()) ?? $default;
        }

        return $default;
    }

    /**
     * Dossier personnel de l'utilisateur courant.
     */
    public function homeDirectory(): string
    {
        if ($this->isWindows()) {
            $home = $this->env('USERPROFILE') ?? $this->env('HOME');
            if ($home !== null) {
                return $home;
            }

            $homeDrive = $this->env('HOMEDRIVE');
            $homePath = $this->env('HOMEPATH');

            if ($homeDrive !== null && $homePath !== null) {
                return $homeDrive.$homePath;
            }
        } else {
            $home = $this->env('HOME') ?? $this->posixHomeDirectory();
            if ($home !== null) {
                return $home;
            }
        }

        throw new RuntimeException('Unable to determine the user\'s home directory.');
    }

    /**
     * Chemin absolu dans le dossier unique de l'application : ~/.{app} (~/.opendb).
     * Ne crée rien sur le disque, contrairement aux méthodes qui renvoient un disque.
     *
     * @param  string  ...$segments  Sous-dossiers ou fichier à ajouter au chemin
     */
    public function applicationPath(string ...$segments): string
    {
        return $this->joinPaths($this->homeDirectory(), $this->appDirectoryName(), ...$segments);
    }

    /**
     * Disque du dossier unique de l'application : ~/.{app}
     * Tout ce que l'application écrit (base SQLite, configuration, cache, logs, fichiers temporaires) s'y trouve.
     * Le dossier est créé s'il n'existe pas encore.
     */
    public function dataDirectory(): FilesystemAdapter
    {
        return $this->disk();
    }

    /**
     * Disque des fichiers de configuration de l'application : ~/.{app}/config
     * Le dossier est créé s'il n'existe pas encore.
     */
    public function configDirectory(): FilesystemAdapter
    {
        return $this->disk('config');
    }

    /**
     * Disque du cache de l'application (son contenu doit pouvoir être supprimé sans perte) : ~/.{app}/cache
     * Le dossier est créé s'il n'existe pas encore.
     */
    public function cacheDirectory(): FilesystemAdapter
    {
        return $this->disk('cache');
    }

    /**
     * Disque des fichiers de logs de l'application : ~/.{app}/logs
     * Le dossier est créé s'il n'existe pas encore.
     */
    public function logDirectory(): FilesystemAdapter
    {
        return $this->disk('logs');
    }

    /**
     * Disque d'un dossier temporaire privé de l'application : ~/.{app}/tmp/{name}
     * À préférer à tempDirectory() pour les fichiers sensibles générés à la volée.
     * Le dossier est créé s'il n'existe pas encore, sa suppression reste à la charge de l'appelant.
     *
     * @param  string  $name  Nom relatif du dossier, par exemple l'identifiant d'une opération
     *
     * @throws InvalidArgumentException Si le nom n'est pas un chemin relatif valide
     */
    public function temporaryDirectory(string $name): FilesystemAdapter
    {
        return $this->disk('tmp', ...$this->relativeSegments($name));
    }

    /**
     * Chemin absolu du fichier SQLite de l'application, dans dataDirectory().
     * Le dossier parent est créé s'il n'existe pas encore, pas le fichier lui-même.
     */
    public function databasePath(string $filename = 'database.sqlite'): string
    {
        return $this->dataDirectory()->path($filename);
    }

    /**
     * Chemin absolu d'un fichier de log de l'application, dans logDirectory().
     * Le dossier parent est créé s'il n'existe pas encore, pas le fichier lui-même.
     *
     * @param  string  $filename  Nom relatif du fichier, sous-dossiers acceptés (cloning/2026-10-07_a1b2c3.log)
     *
     * @throws InvalidArgumentException Si le nom n'est pas un chemin relatif valide
     */
    public function logPath(string $filename): string
    {
        $segments = $this->relativeSegments($filename);
        $file = array_pop($segments);

        return $this->disk('logs', ...$segments)->path($file);
    }

    /**
     * Dossier temporaire du système, partagé entre tous les utilisateurs sous Linux (/tmp).
     */
    public function tempDirectory(): string
    {
        return sys_get_temp_dir();
    }

    /**
     * Construit un disque sur un sous-dossier de l'application, avec les options du disque « opendb ».
     * Le dossier racine du disque est créé à la construction, avec un accès réservé à l'utilisateur courant.
     */
    protected function disk(string ...$segments): FilesystemAdapter
    {
        /** @var FilesystemAdapter $disk */
        $disk = Storage::build([
            ...config('filesystems.disks.opendb'),
            'root' => $this->applicationPath(...$segments),
        ]);

        return $disk;
    }

    /**
     * Nom du dossier de l'application : nom en minuscules précédé d'un point (.opendb).
     *
     * @throws RuntimeException Si le nom de l'application ne donne aucun nom de dossier exploitable
     */
    protected function appDirectoryName(): string
    {
        $slug = Str::slug($this->appName ??= (string) config('app.name'));

        if ($slug === '') {
            throw new RuntimeException("Unable to derive a directory name from the application name [{$this->appName}].");
        }

        return '.'.$slug;
    }

    /**
     * Ramène une locale système (fr_FR.UTF-8, fr-FR, zh_Hans_CN, sr_RS@latin) au format langue_PAYS.
     * Renvoie null pour une valeur vide ou sans langue, comme les locales « C » et « POSIX ».
     */
    protected function normalizeLocale(?string $locale): ?string
    {
        if ($locale === null || ! preg_match('/^([a-z]{2,3})(?:[_-][a-z]{4})?(?:[_-]([a-z]{2}|\d{3}))?(?=$|[_\-.@])/i', $locale, $matches)) {
            return null;
        }

        $language = strtolower($matches[1]);

        return isset($matches[2]) ? $language.'_'.strtoupper($matches[2]) : $language;
    }

    protected function posixHomeDirectory(): ?string
    {
        if ($this->environment !== null || ! function_exists('posix_getpwuid') || ! function_exists('posix_getuid')) {
            return null;
        }

        $info = posix_getpwuid(posix_getuid());

        return empty($info['dir']) ? null : $info['dir'];
    }

    protected function env(string $key): ?string
    {
        $value = $this->environment === null ? getenv($key) : ($this->environment[$key] ?? false);

        return $value === false || $value === '' ? null : $value;
    }

    /**
     * Découpe un chemin relatif en segments, en refusant tout ce qui permettrait de sortir du dossier de base.
     *
     * @return list<string>
     *
     * @throws InvalidArgumentException Si le chemin est vide, absolu ou contient « . » ou « .. »
     */
    protected function relativeSegments(string $relativePath): array
    {
        $segments = preg_split('#[/\\\\]#', $relativePath);

        foreach ($segments as $segment) {
            if (in_array($segment, ['', '.', '..'], true)) {
                throw new InvalidArgumentException("Invalid relative path [{$relativePath}].");
            }
        }

        return $segments;
    }

    protected function joinPaths(string $base, string ...$segments): string
    {
        $separator = $this->isWindows() ? '\\' : '/';

        return implode($separator, [rtrim($base, '/\\'), ...$segments]);
    }
}
