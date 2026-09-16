<?php

declare(strict_types=1);

namespace App\Services\SSH;

use Closure;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

use function in_array;
use function is_readable;
use function sprintf;

final class SshTunnel
{
    public ?int $usedPort = null;

    private const string CONTROL_SOCKET_PATH = '~/.ssh/master-%r@%h:%p';

    /**
     * ssh password not implemented by design (use ssh keys)
     */
    public function __construct(
        private readonly string $sshHost,
        private readonly Remote $remote,
        private readonly string $sshUser = '',
        private readonly int $sshPort = 22,
        /**
         * Path to a private key file (-i). Must be an absolute path:
         * PHP does not expand "~", unlike a shell. Pass e.g.
         * $_SERVER['HOME'] . '/.ssh/id_ed25519' rather than '~/.ssh/id_ed25519'.
         * If null, ssh falls back to its own agent/config resolution.
         */
        private readonly ?string $identityFile = null,
        /**
         * Local port to bind to. If not set, a free port will be used.
         * If a given port is already in use, an exception will be thrown.
         */
        private readonly ?int $localPort = null,
        /**
         * Time after which the master connection will be automatically closed if no connections are open.
         * units: ⟨none⟩/s/S seconds, m/M minutes, h/H hours, d/D days, w/W weeks
         * examples: 600 (600s), 10m (10 minutes), 1h30m (90 minutes)
         *
         * @see https://man7.org/linux/man-pages/man5/sshd_config.5.html#TIME_FORMATS
         */
        private readonly string $autoDisconnectTimeout = '10s',
        /**
         * If true, the tunnel (the -L forward) will be closed after the garbage collector
         * destroys this object. The master connection itself is left alone; it closes on
         * its own after autoDisconnectTimeout of inactivity (ControlPersist).
         */
        private readonly bool $disconnectAfterTunnelDestroyed = true,
        /**
         * Optional callback for status/debug messages: function(string $message, bool $isError): void
         * Left null by default so this class never writes to STDOUT/STDERR on its own -
         * important if your process's stdout is a protocol channel (e.g. JSON-RPC over stdio).
         * Pass a closure to a PSR-3 logger, a file writer, fwrite(STDERR, ...), etc.
         */
        private readonly ?Closure $logger = null,
    ) {
        if (! preg_match('/^(\d+[smhdw])+$/i', $this->autoDisconnectTimeout)) {
            throw new InvalidArgumentException('Invalid autoDisconnectTimeout format given. Must be in the format of ⟨number⟩[s|m|h|d|w] (given: "'.$this->autoDisconnectTimeout.'")');
        }

        if ($this->localPort !== null && ($this->localPort < 1 || $this->localPort > 65535)) {
            throw new InvalidArgumentException('Invalid localPort given. Must be in the range of 1-65535 (given: '.$this->localPort.')');
        }

        if ($this->sshPort < 1 || $this->sshPort > 65535) {
            throw new InvalidArgumentException('Invalid sshPort given. Must be in the range of 1-65535 (given: '.$this->sshPort.')');
        }

        if ($this->identityFile !== null && ! is_readable($this->identityFile)) {
            throw new InvalidArgumentException('Identity file not readable (must be an absolute path, "~" is not expanded): '.$this->identityFile);
        }
    }

    /**
     * @return int returns the local port
     *
     * @throws RuntimeException
     */
    public function start(): int
    {
        $this->startMasterIfNotExists();

        $this->usedPort ??= $this->localPort ?? $this->getFreePort();

        $process = new Process($this->controlCommand('forward', [
            '-o', 'ExitOnForwardFailure=yes',
            '-L', $this->usedPort.':'.$this->remote->path,
        ]));
        $process->mustRun();

        register_shutdown_function([$this, 'stop']);

        return $this->usedPort;
    }

    private function startMasterIfNotExists(): void
    {
        if ($this->isMasterRunning()) {
            $this->log('SSH master already running.');

            return;
        }

        $this->log('Starting SSH master...');
        $start = hrtime(true);

        $process = new Process($this->masterCommand());
        $process->mustRun(function (string $type, string $buffer): void {
            $this->log(rtrim($buffer), $type === Process::ERR);
        });

        $elapsed = (hrtime(true) - $start) / 1_000_000_000;
        $this->log(sprintf('SSH master started in %.3fs', $elapsed));

        if ($this->isMasterRunning()) {
            return;
        }

        throw new RuntimeException('Could not start SSH master');
    }

    private function getFreePort(int $startPort = 49152, int $maxPort = 65535): int
    {
        for ($port = $startPort; $port <= $maxPort; $port++) {
            $socket = @stream_socket_server('tcp://127.0.0.1:'.$port, $errno, $errstr);

            if ($socket) {
                fclose($socket); // Port is free

                return $port;
            }
        }

        throw new RuntimeException('Could not find a free port between '.$startPort.' and '.$maxPort);
    }

    /** @return list<string> */
    private function commonArgs(): array
    {
        $args = [
            // Never let ssh block waiting for a password/passphrase prompt that has
            // nowhere to go (no TTY here) - fail fast with a clear error instead.
            '-o', 'BatchMode=yes',
        ];

        if ($this->identityFile !== null) {
            $args[] = '-i';
            $args[] = $this->identityFile;
            // Without this, ssh silently falls back to keys offered by a running
            // ssh-agent or to the default ~/.ssh/id_* files, even if identityFile
            // points to a wrong or unrelated key. This forces ssh to use ONLY
            // the key given above.
            $args[] = '-o';
            $args[] = 'IdentitiesOnly=yes';
        }

        if ($this->sshPort !== 22) {
            $args[] = '-p';
            $args[] = (string) $this->sshPort;
        }

        return $args;
    }

    /** @return list<string> */
    private function masterCommand(): array
    {
        $args = ['ssh', ...$this->commonArgs()];

        if ($this->logger !== null) {
            $args[] = '-v';
        }

        return [
            ...$args,
            '-f',
            '-N',
            '-o', 'ControlMaster=auto',
            '-o', 'ControlPersist='.$this->autoDisconnectTimeout,
            '-S', self::CONTROL_SOCKET_PATH,
            $this->destination(),
        ];
    }

    /**
     * @param  list<string>  $extra
     * @return list<string>
     */
    private function controlCommand(string $sshControlCommand, array $extra = []): array
    {
        return [
            'ssh',
            ...$this->commonArgs(),
            '-S', self::CONTROL_SOCKET_PATH,
            '-O', $sshControlCommand,
            ...$extra,
            $this->destination(),
        ];
    }

    private function destination(): string
    {
        return $this->sshUser !== '' ? "{$this->sshUser}@{$this->sshHost}" : $this->sshHost;
    }

    private function log(string $message, bool $isError = false): void
    {
        if ($this->logger !== null) {
            ($this->logger)($message, $isError);
        }
    }

    private function isMasterRunning(): bool
    {
        $process = new Process($this->controlCommand('check'));
        $process->run();

        if (! in_array($process->getExitCode(), [0, 255], true)) {
            throw new ProcessFailedException($process);
        }

        $isSuccessful = $process->isSuccessful();
        if (! $isSuccessful) {
            $this->usedPort = null;
        }

        return $isSuccessful;
    }

    public function __destruct()
    {
        if ($this->disconnectAfterTunnelDestroyed) {
            $this->stop();
        }
    }

    public function stop(): void
    {
        if (! $this->usedPort || ! $this->isMasterRunning()) {
            return;
        }

        $process = new Process($this->controlCommand('cancel', [
            '-L', $this->usedPort.':'.$this->remote->path,
        ]));
        $process->mustRun();
        $this->usedPort = null;
    }

    /**
     * This should not be needed in any way. The master is terminated automatically after
     * autoDisconnectTimeout of inactivity (ControlPersist).
     */
    public function stopMaster(): void
    {
        if (! $this->isMasterRunning()) {
            return;
        }

        new Process($this->controlCommand('exit'))->mustRun();
    }
}
