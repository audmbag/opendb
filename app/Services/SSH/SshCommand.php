<?php

declare(strict_types=1);

namespace App\Services\SSH;

use Illuminate\Contracts\Process\ProcessResult;
use RuntimeException;

/**
 * The ssh client of the system, reaching one server with one key. The same arguments are given to every ssh process
 * started for the server, on every system.
 */
final readonly class SshCommand
{
    /**
     * The exit code of a process whose program cannot be found, on Linux and macOS.
     */
    private const int NOT_FOUND_EXIT_CODE = 127;

    public function __construct(
        public string $host,
        public string $user,
        public int $port,
        public ?string $identityFile,
    ) {}

    /**
     * @param  list<string>  $arguments  placed before the destination
     * @return list<string>
     */
    public function line(array $arguments = []): array
    {
        return ['ssh', ...$this->commonArguments(), ...$arguments, $this->destination()];
    }

    /**
     * The server, as `user@host`.
     */
    public function destination(): string
    {
        return $this->user !== '' ? "{$this->user}@{$this->host}" : $this->host;
    }

    /**
     * Fails with a message the user can act on when ssh itself is missing, rather than with the bare exit code.
     */
    public static function failWhenMissing(ProcessResult $result): void
    {
        if ($result->exitCode() === self::NOT_FOUND_EXIT_CODE) {
            throw self::missing();
        }
    }

    public static function missing(): RuntimeException
    {
        return new RuntimeException('The ssh command cannot be found: install the OpenSSH client to reach a database through an SSH tunnel.');
    }

    /** @return list<string> */
    private function commonArguments(): array
    {
        $arguments = [
            // Never let ssh block waiting for a password/passphrase prompt that has
            // nowhere to go (no TTY here) - fail fast with a clear error instead.
            '-o', 'BatchMode=yes',
        ];

        if ($this->identityFile !== null) {
            // Without IdentitiesOnly, ssh silently falls back to keys offered by a running ssh-agent or to the default
            // ~/.ssh/id_* files, even if identityFile points to a wrong or unrelated key.
            array_push($arguments, '-i', $this->identityFile, '-o', 'IdentitiesOnly=yes');
        }

        if ($this->port !== 22) {
            array_push($arguments, '-p', (string) $this->port);
        }

        return $arguments;
    }
}
