<?php

declare(strict_types=1);

namespace App\Services\SSH\Transports;

use App\Services\SSH\LocalPort;
use App\Services\SSH\Remote;
use App\Services\SSH\SshCommand;
use Closure;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Sleep;
use RuntimeException;

/**
 * Windows: its ssh client has no master connection to share (ControlMaster), so each forward is an ssh process of its
 * own, kept open between the requests and shared by every process that needs the same database.
 *
 * A watchdog in PowerShell, hidden and detached from the console, starts ssh and plays the part of ControlPersist: it
 * ends ssh once no connection has gone through the forward for the delay. Its state lies in files next to each other,
 * which every process can read: `.json` (port and watchdog), `.log` (what ssh says), `.ready`, `.alive` (touched by
 * the watchdog every 5 seconds) and `.exit` (written by the watchdog when ssh ends).
 */
final readonly class WindowsTransport implements TunnelTransport
{
    /**
     * What ssh writes once it listens on the local port, which only happens after the server accepted the key.
     */
    private const string LISTENING = 'Local forwarding listening on';

    /**
     * A watchdog that has not touched its file for this long is gone, with the machine restarted for instance.
     */
    private const int STALE_SECONDS = 15;

    private const int START_TIMEOUT_MILLISECONDS = 30_000;

    private const int POLL_MILLISECONDS = 100;

    /**
     * Starts the watchdog in its own hidden window, so that it outlives this process and Ctrl+C in the console, then
     * gives its process id.
     */
    private const string LAUNCHER = <<<'POWERSHELL'
        $watchdog = Start-Process -FilePath 'powershell.exe' -WindowStyle Hidden -PassThru -ArgumentList '-NoProfile', '-NonInteractive', '-ExecutionPolicy', 'Bypass', '-EncodedCommand', $env:OPENDB_SSH_WATCHDOG
        $watchdog.Id
        POWERSHELL;

    private const string WATCHDOG = <<<'POWERSHELL'
        $ErrorActionPreference = 'Stop'
        $state = $env:OPENDB_SSH_STATE
        $status = 'not-found'
        $ssh = $null
        try {
            $start = New-Object System.Diagnostics.ProcessStartInfo
            $start.FileName = 'ssh'
            $start.Arguments = $env:OPENDB_SSH_ARGUMENTS
            $start.UseShellExecute = $false
            $start.CreateNoWindow = $true
            $ssh = [System.Diagnostics.Process]::Start($start)
            $status = 'failed'
            $port = [int] $env:OPENDB_SSH_PORT
            $idleSeconds = [int] $env:OPENDB_SSH_IDLE
            $lastUse = Get-Date
            do {
                [System.IO.File]::WriteAllText("$state.alive", '')
                if (@(Get-NetTCPConnection -LocalPort $port -State Established -ErrorAction SilentlyContinue).Count -gt 0) {
                    $lastUse = Get-Date
                } elseif (((Get-Date) - $lastUse).TotalSeconds -ge $idleSeconds) {
                    $ssh.Kill()
                }
            } until ($ssh.WaitForExit(5000))
            $status = [string] $ssh.ExitCode
        } finally {
            if ($ssh -ne $null -and -not $ssh.HasExited) {
                $ssh.Kill()
            }
            [System.IO.File]::WriteAllText("$state.exit", $status)
            Remove-Item -LiteralPath "$state.alive" -ErrorAction SilentlyContinue
        }
        POWERSHELL;

    /**
     * @param  string  $persist  how long the forward stays open without any connection, as ControlPersist reads it (15m)
     * @param  string  $stateDirectory  where the files of the watchdogs are
     */
    public function __construct(
        private SshCommand $ssh,
        private Remote $remote,
        private string $persist,
        private string $stateDirectory,
    ) {}

    public function forward(?int $localPort): int
    {
        return $this->exclusively(fn (): int => $this->openPort() ?? $this->launch($localPort ?? LocalPort::free()));
    }

    public function cancel(int $localPort): void
    {
        // Other processes may use the forward too: the watchdog ends it once nothing goes through it any more.
    }

    public function isRunning(): bool
    {
        return $this->openPort() !== null;
    }

    public function close(): void
    {
        $this->exclusively($this->terminate(...));
    }

    /**
     * The port of the forward another process already opened, while its ssh still runs.
     */
    private function openPort(): ?int
    {
        $state = json_decode((string) @file_get_contents($this->file('json')), true);

        if (! is_array($state) || ! is_file($this->file('ready')) || is_file($this->file('exit'))) {
            return null;
        }

        clearstatcache(true, $this->file('alive'));
        $heartbeat = @filemtime($this->file('alive'));

        return $heartbeat !== false && $heartbeat >= time() - self::STALE_SECONDS ? (int) $state['port'] : null;
    }

    private function launch(int $port): int
    {
        $this->forget();

        $watchdog = Process::env([
            'OPENDB_SSH_ARGUMENTS' => WindowsCommandLine::join(array_slice($this->ssh->line([
                '-v',
                '-N',
                '-E', $this->file('log'),
                '-o', 'ExitOnForwardFailure=yes',
                // A connection the network dropped makes ssh end, and the next request opens another one.
                '-o', 'ServerAliveInterval=30',
                '-L', $port.':'.$this->remote->path,
            ]), 1)),
            'OPENDB_SSH_STATE' => $this->stem(),
            'OPENDB_SSH_PORT' => (string) $port,
            'OPENDB_SSH_IDLE' => (string) $this->idleSeconds(),
            'OPENDB_SSH_WATCHDOG' => self::encode(self::WATCHDOG),
        ])->run(self::powershell(self::LAUNCHER))->throw()->output();

        file_put_contents($this->file('json'), json_encode(['port' => $port, 'watchdog' => (int) trim($watchdog)]));

        $this->waitUntilListening();

        return $port;
    }

    private function waitUntilListening(): void
    {
        for ($waited = 0; $waited < self::START_TIMEOUT_MILLISECONDS; $waited += self::POLL_MILLISECONDS) {
            if (is_file($this->file('exit'))) {
                $failure = $this->failure();
                $this->forget();

                throw $failure;
            }

            if (str_contains((string) @file_get_contents($this->file('log')), self::LISTENING)) {
                touch($this->file('ready'));

                return;
            }

            Sleep::for(self::POLL_MILLISECONDS)->milliseconds();
        }

        $this->terminate();

        throw new RuntimeException('The SSH tunnel to '.$this->ssh->destination().' did not open within '.(self::START_TIMEOUT_MILLISECONDS / 1000).' seconds.');
    }

    /**
     * Why ssh ended: the last lines it wrote which are not debugging details.
     */
    private function failure(): RuntimeException
    {
        $status = trim((string) @file_get_contents($this->file('exit')));

        if ($status === 'not-found') {
            return SshCommand::missing();
        }

        $errors = (@file($this->file('log'), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [])
            |> (fn (array $lines): array => array_filter($lines, fn (string $line): bool => ! str_starts_with($line, 'debug') && ! str_starts_with($line, 'OpenSSH')))
            |> (fn (array $lines): string => implode(' ', array_slice($lines, -3)));

        return new RuntimeException('Could not open the SSH tunnel to '.$this->ssh->destination().': '.($errors !== '' ? $errors : 'ssh exited with code '.$status));
    }

    /**
     * Ends the watchdog and its ssh, without taking the lock: the caller holds it.
     */
    private function terminate(): void
    {
        $watchdog = json_decode((string) @file_get_contents($this->file('json')), true)['watchdog'] ?? null;

        if ($watchdog !== null) {
            Process::run(['taskkill', '/PID', (string) $watchdog, '/T', '/F']);
        }

        $this->forget();
    }

    private function forget(): void
    {
        foreach (['json', 'ready', 'alive', 'exit', 'log'] as $extension) {
            @unlink($this->file($extension));
        }
    }

    /**
     * Two processes needing the same database at the same time open a single forward.
     *
     * @template TResult
     *
     * @param  Closure(): TResult  $callback
     * @return TResult
     */
    private function exclusively(Closure $callback): mixed
    {
        $lock = fopen($this->file('lock'), 'c');
        flock($lock, LOCK_EX);

        try {
            return $callback();
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function file(string $extension): string
    {
        return $this->stem().'.'.$extension;
    }

    /**
     * The files of the forward: one per server, key and database, whichever project or user needs it.
     */
    private function stem(): string
    {
        return $this->stateDirectory.DIRECTORY_SEPARATOR
            .sha1(implode('|', [$this->ssh->destination(), $this->ssh->port, $this->ssh->identityFile, $this->remote->path]));
    }

    private function idleSeconds(): int
    {
        preg_match_all('/(\d+)([smhdw])/i', $this->persist, $parts, PREG_SET_ORDER);

        return array_sum(array_map(fn (array $part): int => (int) $part[1] * match (strtolower($part[2])) {
            's' => 1,
            'm' => 60,
            'h' => 3_600,
            'd' => 86_400,
            'w' => 604_800,
        }, $parts));
    }

    /**
     * @return list<string>
     */
    private static function powershell(string $script): array
    {
        return ['powershell.exe', '-NoProfile', '-NonInteractive', '-ExecutionPolicy', 'Bypass', '-EncodedCommand', self::encode($script)];
    }

    /**
     * PowerShell reads an encoded script in UTF-16LE: nothing in it needs quoting on the command line.
     */
    private static function encode(string $script): string
    {
        return base64_encode(mb_convert_encoding($script, 'UTF-16LE', 'UTF-8'));
    }
}
