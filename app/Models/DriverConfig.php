<?php

namespace App\Models;

use App\Database\Contracts\DriverContract;
use App\Database\Drivers\BaseDriver;
use App\Services\Privileges\PrivilegeManager;
use App\Services\SSH\SshTunnel;
use Illuminate\Database\Eloquent\Attributes\Appends;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

#[Fillable(['driver_id', 'host', 'port', 'database', 'username', 'password', 'ssh', 'disabled_tables', 'read_only'])]
#[Appends(['connection_string'])]
#[WithoutTimestamps]
class DriverConfig extends Model
{
    protected function casts(): array
    {
        return [
            'port' => 'integer',
            'password' => 'encrypted',
            'host' => 'encrypted',
            'database' => 'encrypted',
            'username' => 'encrypted',
            'ssh' => 'encrypted:array',
            'disabled_tables' => 'encrypted:array',
            // Only reads run in the SQL editor of the project, and their results cannot be changed there.
            'read_only' => 'boolean',
        ];
    }

    /**
     * Leave out of what is sent to the page the password, the SSH tunnel and the tables hidden from the agent.
     */
    public function withoutSecrets(): static
    {
        return $this->makeHidden(['password', 'ssh', 'disabled_tables']);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'driver_id', 'id');
    }

    public function getDriver(): DriverContract
    {
        return BaseDriver::for($this->connectionConfig());
    }

    /**
     * The tunnel to the database through its SSH server, not started, or null when the database is reached directly.
     *
     * @throws InvalidArgumentException when the SSH configuration is incomplete, or its key cannot be read
     */
    public function sshTunnel(): ?SshTunnel
    {
        return empty($this->ssh) ? null : BaseDriver::sshTunnelFor($this->connectionConfig());
    }

    /**
     * @return array{host: ?string, port: mixed, database: ?string, username: ?string, password: ?string, driver_id: ?string, ssh: mixed}
     */
    private function connectionConfig(): array
    {
        return [
            'host' => $this->host,
            'port' => $this->port,
            'database' => $this->database,
            'username' => $this->username,
            'password' => $this->password,
            'driver_id' => $this->driver_id,
            'ssh' => $this->ssh,
        ];
    }

    /**
     * Le driver de l'agent : les tables désactivées dans les paramètres du projet lui sont cachées.
     */
    public function getAgentDriver(): DriverContract
    {
        return $this->getDriver()->hideTables($this->disabled_tables ?? []);
    }

    /**
     * The driver of a user: the tables they hold no privilege on are hidden, and their queries checked against their privileges.
     */
    public function driverFor(User $user): DriverContract
    {
        return $this->withPrivilegesOf($user, $this->getDriver());
    }

    /**
     * The driver of the agent of a user, which never does more than the user: the tables disabled for the agent are hidden too.
     */
    public function agentDriverFor(User $user): DriverContract
    {
        return $this->withPrivilegesOf($user, $this->getAgentDriver());
    }

    private function withPrivilegesOf(User $user, DriverContract $driver): DriverContract
    {
        $privileges = app(PrivilegeManager::class)->for($user)->tablePrivileges((string) $this->project_id);

        return $privileges->unrestricted ? $driver : $driver->withPrivileges($privileges, $user->name);
    }

    public function getConnectionStringAttribute(): string
    {
        return $this->getDriver()->getConnectionString();
    }

    public function getTablesInfoAttribute(): array
    {
        return $this->getDriver()->listTables(mapper: fn (array $table): array => [
            'name' => $table['name'],
            'columns' => count($table['columns']),
            'agentAccessEnabled' => ! in_array($table['name'], $this->disabled_tables ?? []),
            'cloningIncludingEnabled' => ! in_array($table['name'], $this->disabled_tables ?? []),
        ]);
    }
}
