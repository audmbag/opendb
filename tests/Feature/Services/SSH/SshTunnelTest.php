<?php

use App\Enums\OperatingSystemEnum;
use App\Services\SSH\Remote;
use App\Services\SSH\SshTunnel;
use App\Services\SystemInfoService;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Sleep;

beforeEach(function () {
    $this->home = realpath(sys_get_temp_dir()).'/'.uniqid('opendb-ssh-');
    mkdir($this->home);
});

afterEach(function () {
    // The Windows paths joined with backslashes make a single folder next to the home on Linux.
    File::deleteDirectory(app(SystemInfoService::class)->applicationPath('tmp', 'ssh'));
    File::deleteDirectory($this->home);
});

function onSystem(OperatingSystemEnum $os): void
{
    app()->instance(SystemInfoService::class, new SystemInfoService($os, 'OpenDB', ['HOME' => test()->home, 'USERPROFILE' => test()->home]));
}

function databaseTunnel(): SshTunnel
{
    return new SshTunnel(
        sshHost: 'db.example.com',
        remote: Remote::tcp(3306),
        sshUser: 'deploy',
        sshPort: 2222,
        localPort: 50001,
        autoDisconnectTimeout: '15m',
        disconnectAfterTunnelDestroyed: false,
    );
}

/**
 * The fake of the watchdog: ssh listens at once, or writes why it ended.
 */
function fakeWatchdog(bool $listens = true, ?string $exitStatus = null, string $log = ''): void
{
    Process::fake([
        '*powershell.exe*' => function (PendingProcess $process) use ($listens, $exitStatus, $log) {
            $state = $process->environment['OPENDB_SSH_STATE'];

            file_put_contents("{$state}.log", $log.($listens ? "debug1: Local forwarding listening on 127.0.0.1 port 50001.\n" : ''));
            touch("{$state}.alive");

            if ($exitStatus !== null) {
                file_put_contents("{$state}.exit", $exitStatus);
            }

            return Process::result('4242');
        },
        '*' => Process::result(),
    ]);
}

function ranWatchdog(): Closure
{
    return fn (PendingProcess $process): bool => $process->command[0] === 'powershell.exe';
}

describe('Linux and macOS', function () {
    beforeEach(fn () => onSystem(OperatingSystemEnum::LINUX));

    it('starts a master in the folder of the application, then forwards the port through it', function () {
        Process::fake([
            '*-O*check*' => Process::sequence([Process::result(exitCode: 255)])->dontFailWhenEmpty(),
            '*' => Process::result(),
        ]);
        $socket = app(SystemInfoService::class)->applicationPath('tmp', 'ssh').'/%C';

        expect(databaseTunnel()->start())->toBe(50001);

        Process::assertRan(fn (PendingProcess $process): bool => $process->command === [
            'ssh', '-o', 'BatchMode=yes', '-p', '2222', '-f', '-N', '-o', 'ControlMaster=auto', '-o', 'ControlPersist=15m', '-S', $socket, 'deploy@db.example.com',
        ]);
        Process::assertRan(fn (PendingProcess $process): bool => $process->command === [
            'ssh', '-o', 'BatchMode=yes', '-p', '2222', '-S', $socket, '-O', 'forward', '-o', 'ExitOnForwardFailure=yes', '-L', '50001:127.0.0.1:3306', 'deploy@db.example.com',
        ]);
    });

    it('forwards the port through the master already running', function () {
        Process::fake();

        databaseTunnel()->start();

        Process::assertDidntRun(fn (PendingProcess $process): bool => in_array('ControlMaster=auto', $process->command, true));
    });

    it('gives its port back to the master when stopped', function () {
        Process::fake();
        $tunnel = databaseTunnel();
        $tunnel->start();

        $tunnel->stop();

        Process::assertRan(fn (PendingProcess $process): bool => array_slice($process->command, -5) === ['-O', 'cancel', '-L', '50001:127.0.0.1:3306', 'deploy@db.example.com']);
    });

    it('explains that ssh is missing', function () {
        Process::fake(['*' => Process::result(exitCode: 127)]);

        expect(fn () => databaseTunnel()->start())->toThrow(RuntimeException::class, 'The ssh command cannot be found');
    });
});

describe('Windows', function () {
    beforeEach(fn () => onSystem(OperatingSystemEnum::WINDOWS));

    it('starts ssh through a watchdog that closes it after the delay without connections', function () {
        fakeWatchdog();

        expect(databaseTunnel()->start())->toBe(50001);

        Process::assertRan(function (PendingProcess $process): bool {
            $state = $process->environment['OPENDB_SSH_STATE'] ?? null;

            return $process->command[0] === 'powershell.exe'
                && $process->environment['OPENDB_SSH_ARGUMENTS'] === "-o BatchMode=yes -p 2222 -v -N -E {$state}.log -o ExitOnForwardFailure=yes -o ServerAliveInterval=30 -L 50001:127.0.0.1:3306 deploy@db.example.com"
                && $process->environment['OPENDB_SSH_PORT'] === '50001'
                && $process->environment['OPENDB_SSH_IDLE'] === '900';
        });
    });

    it('shares the forward with the other processes, and leaves it open when one of them stops', function () {
        fakeWatchdog();
        $first = databaseTunnel();
        $first->start();
        $first->stop();

        expect(databaseTunnel()->start())->toBe(50001);

        Process::assertRanTimes(ranWatchdog(), 1);
        Process::assertDidntRun(fn (PendingProcess $process): bool => $process->command[0] === 'taskkill');
    });

    it('starts ssh again once its watchdog is gone', function () {
        fakeWatchdog();
        databaseTunnel()->start();
        foreach (File::files(app(SystemInfoService::class)->applicationPath('tmp', 'ssh')) as $file) {
            if ($file->getExtension() === 'alive') {
                touch($file->getPathname(), time() - 60);
            }
        }

        databaseTunnel()->start();

        Process::assertRanTimes(ranWatchdog(), 2);
    });

    it('reports why ssh could not reach the server', function () {
        Sleep::fake();
        fakeWatchdog(listens: false, exitStatus: '255', log: "OpenSSH_for_Windows_9.5p1\ndebug1: Connecting to db.example.com port 2222.\ndeploy@db.example.com: Permission denied (publickey).\n");

        expect(fn () => databaseTunnel()->start())
            ->toThrow(RuntimeException::class, 'Could not open the SSH tunnel to deploy@db.example.com: deploy@db.example.com: Permission denied (publickey).');
    });

    it('explains that ssh is missing', function () {
        Sleep::fake();
        fakeWatchdog(listens: false, exitStatus: 'not-found');

        expect(fn () => databaseTunnel()->start())->toThrow(RuntimeException::class, 'The ssh command cannot be found');
    });

    it('ends the watchdog when ssh does not listen in time', function () {
        Sleep::fake();
        fakeWatchdog(listens: false);

        expect(fn () => databaseTunnel()->start())->toThrow(RuntimeException::class, 'did not open within 30 seconds');

        Process::assertRan(fn (PendingProcess $process): bool => $process->command === ['taskkill', '/PID', '4242', '/T', '/F']);
    });

    it('ends the watchdog and its ssh when the connection is closed', function () {
        fakeWatchdog();
        databaseTunnel()->start();

        databaseTunnel()->stopMaster();

        Process::assertRan(fn (PendingProcess $process): bool => $process->command === ['taskkill', '/PID', '4242', '/T', '/F']);
        expect(databaseTunnel()->start())->toBe(50001);
        Process::assertRanTimes(ranWatchdog(), 2);
    });
});
