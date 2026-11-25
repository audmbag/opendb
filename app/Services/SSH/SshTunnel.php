<?php

declare(strict_types=1);

namespace App\Services\SSH;

use App\Services\SSH\Transports\MultiplexedTransport;
use App\Services\SSH\Transports\TunnelTransport;
use App\Services\SSH\Transports\WindowsTransport;
use App\Services\SystemInfoService;
use Closure;
use InvalidArgumentException;
use RuntimeException;

use function is_readable;

/**
 * Passerelle entre l'application et le tunnel SSH vers une base de données : un port local transféré vers la base à
 * travers son serveur SSH. La façon de garder la connexion au serveur ouverte entre les processus dépend du système
 * (MultiplexedTransport sous Linux et macOS, WindowsTransport sous Windows).
 */
final class SshTunnel
{
    public ?int $usedPort = null;

    private ?TunnelTransport $transport = null;

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
         * Time after which the connection to the server is closed if no connection goes through it any more.
         * units: ⟨none⟩/s/S seconds, m/M minutes, h/H hours, d/D days, w/W weeks
         * examples: 600 (600s), 10m (10 minutes), 1h30m (90 minutes)
         *
         * @see https://man7.org/linux/man-pages/man5/sshd_config.5.html#TIME_FORMATS
         */
        private readonly string $autoDisconnectTimeout = '10s',
        /**
         * If true, the forward of this process is given back after the garbage collector destroys this object. The
         * connection to the server itself is left alone; it closes on its own after autoDisconnectTimeout of inactivity.
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
        $this->usedPort = $this->transport()->forward($this->localPort);

        register_shutdown_function([$this, 'stop']);

        return $this->usedPort;
    }

    /**
     * The server the tunnel goes through, as `user@host:port`: the tunnels to the same target share their connection.
     */
    public function target(): string
    {
        return $this->command()->destination().':'.$this->sshPort;
    }

    /**
     * Whether the tunnel was started and the connection to its server still runs.
     */
    public function isOpen(): bool
    {
        return $this->usedPort !== null && $this->transport()->isRunning();
    }

    public function __destruct()
    {
        if ($this->disconnectAfterTunnelDestroyed) {
            $this->stop();
        }
    }

    public function stop(): void
    {
        if ($this->usedPort === null) {
            return;
        }

        $this->transport()->cancel($this->usedPort);
        $this->usedPort = null;
    }

    /**
     * This should not be needed in any way. The connection is closed automatically after autoDisconnectTimeout of
     * inactivity.
     */
    public function stopMaster(): void
    {
        $this->transport()->close();
    }

    private function transport(): TunnelTransport
    {
        if ($this->transport !== null) {
            return $this->transport;
        }

        $system = app(SystemInfoService::class);
        $directory = rtrim($system->temporaryDirectory('ssh')->path(''), '/\\');

        return $this->transport = $system->isWindows()
            ? new WindowsTransport($this->command(), $this->remote, $this->autoDisconnectTimeout, $directory)
            : new MultiplexedTransport($this->command(), $this->remote, $this->autoDisconnectTimeout, $directory, $this->logger);
    }

    private function command(): SshCommand
    {
        return new SshCommand($this->sshHost, $this->sshUser, $this->sshPort, $this->identityFile);
    }
}
