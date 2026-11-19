<?php

declare(strict_types=1);

namespace App\Services\SSH\Transports;

use App\Services\SSH\LocalPort;
use App\Services\SSH\Remote;
use App\Services\SSH\SshCommand;
use Closure;
use Illuminate\Support\Facades\Process;
use RuntimeException;

use function in_array;
use function sprintf;

/**
 * Linux and macOS: one master connection per server (ControlMaster), which every process asks for its own forward.
 * The master closes on its own once no forward has been used for its delay (ControlPersist).
 */
final readonly class MultiplexedTransport implements TunnelTransport
{
    /**
     * @param  string  $socketDirectory  where the sockets of the masters are, short enough for the 104 characters macOS allows
     * @param  Closure(string, bool): void|null  $logger
     */
    public function __construct(
        private SshCommand $ssh,
        private Remote $remote,
        private string $persist,
        private string $socketDirectory,
        private ?Closure $logger = null,
    ) {}

    public function forward(?int $localPort): int
    {
        $this->startMasterIfNotRunning();

        $port = $localPort ?? LocalPort::free();

        Process::run($this->control('forward', ['-o', 'ExitOnForwardFailure=yes', '-L', $port.':'.$this->remote->path]))->throw();

        return $port;
    }

    public function cancel(int $localPort): void
    {
        if ($this->isRunning()) {
            Process::run($this->control('cancel', ['-L', $localPort.':'.$this->remote->path]))->throw();
        }
    }

    public function isRunning(): bool
    {
        $result = Process::run($this->control('check'));

        SshCommand::failWhenMissing($result);

        if (! in_array($result->exitCode(), [0, 255], true)) {
            $result->throw();
        }

        return $result->successful();
    }

    public function close(): void
    {
        if ($this->isRunning()) {
            Process::run($this->control('exit'))->throw();
        }
    }

    private function startMasterIfNotRunning(): void
    {
        if ($this->isRunning()) {
            $this->log('SSH master already running.');

            return;
        }

        $this->log('Starting SSH master...');
        $start = hrtime(true);

        Process::run($this->ssh->line([
            ...($this->logger !== null ? ['-v'] : []),
            '-f',
            '-N',
            '-o', 'ControlMaster=auto',
            '-o', 'ControlPersist='.$this->persist,
            '-S', $this->socket(),
        ]), fn (string $type, string $buffer) => $this->log(rtrim($buffer), $type === 'err'))->throw();

        $this->log(sprintf('SSH master started in %.3fs', (hrtime(true) - $start) / 1_000_000_000));

        if (! $this->isRunning()) {
            throw new RuntimeException('Could not start SSH master');
        }
    }

    /**
     * @param  list<string>  $extra
     * @return list<string>
     */
    private function control(string $command, array $extra = []): array
    {
        return $this->ssh->line(['-S', $this->socket(), '-O', $command, ...$extra]);
    }

    /**
     * The socket of the master: %C is a hash of the user, the server and its port, so every process going through the
     * same server finds the same master, whatever the length of the name of the server.
     */
    private function socket(): string
    {
        return $this->socketDirectory.'/%C';
    }

    private function log(string $message, bool $isError = false): void
    {
        if ($this->logger !== null) {
            ($this->logger)($message, $isError);
        }
    }
}
