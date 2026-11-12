<?php

namespace App\Console\Commands\Settings\Project;

use App\Console\Commands\Settings\ProjectSection;
use App\Database\Contracts\DriverContract;
use App\Database\Enums\DriverLabEnum;
use App\Http\Requests\DriverConfigRequest;
use App\Models\Driver;
use App\Models\DriverConfig;
use App\Models\Project;
use App\Repository\ProjectRepository;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\table;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

/**
 * The connection to the database of the project, only saved once the database answers.
 */
class ConnectionSection extends ProjectSection
{
    public function __construct(protected ProjectRepository $projects) {}

    public function label(): string
    {
        return __('projects.settings.nav.database');
    }

    public function run(Project $project): void
    {
        $config = $project->driver_config;

        $this->showConnection($config);

        if (! confirm(__('projects.settings.database.change_title'), default: false)) {
            return;
        }

        warning(__('projects.settings.database.change_message'));

        $request = $this->validate(DriverConfigRequest::class, $this->askConnection($config));

        if ($request === null) {
            return;
        }

        $driver = $this->reportingValidationErrors(
            fn (): DriverContract => spin($request->connectedDriver(...), __('console.project_settings.connection.testing')),
        );

        if ($driver === null) {
            return;
        }

        $driver->forgetSchema();
        $this->projects->updateConnection($project, $request->driverData());

        info(__('projects.settings.database.saved'));
    }

    private function showConnection(?DriverConfig $config): void
    {
        if ($config === null) {
            return;
        }

        table([__('projects.settings.database.connection'), ''], [
            [__('console.project_settings.connection.driver'), Driver::query()->find($config->driver_id)?->name ?? $config->driver_id],
            [__('projects.status.host'), $config->host ?? '—'],
            [__('projects.status.port'), $config->port ?? '—'],
            [__('projects.status.username'), $config->username ?? '—'],
            [__('projects.status.database'), $config->database],
            [__('projects.form.use_ssh'), $config->ssh['host'] ?? '—'],
        ]);
    }

    /**
     * The answers of a database of the same driver start from the current connection, and an empty password keeps its own.
     *
     * @return array<string, mixed>
     */
    private function askConnection(?DriverConfig $config): array
    {
        $driverId = (string) select(
            __('console.project_settings.connection.driver'),
            Driver::query()->pluck('name', 'id')->all(),
            default: $config?->driver_id,
        );
        $current = $config?->driver_id === $driverId ? $config : null;

        if ($driverId === DriverLabEnum::SQLITE->value) {
            return [
                'driver_id' => $driverId,
                'database' => text(__('console.project_settings.connection.sqlite_file'), default: (string) $current?->database, required: true),
                'create_database_if_not_exists' => confirm(__('projects.form.create_database'), default: false),
            ];
        }

        $answers = [
            'driver_id' => $driverId,
            'host' => text(__('projects.form.host'), placeholder: __('projects.form.host_placeholder'), default: (string) $current?->host, required: true),
            'port' => text(__('projects.form.port'), placeholder: __('projects.form.port_placeholder'), default: (string) $current?->port, required: true),
            'username' => text(__('projects.form.username'), placeholder: __('projects.form.username_placeholder'), default: (string) $current?->username, required: true),
            'password' => password(
                __('projects.form.password'),
                required: $current === null,
                hint: $current !== null ? __('console.project_settings.connection.keep_password') : '',
            ) ?: $current?->password,
            'database' => text(__('projects.form.database'), placeholder: __('projects.form.database_placeholder'), default: (string) $current?->database, required: true),
            'create_database_if_not_exists' => confirm(__('projects.form.create_database'), default: false),
        ];

        if (confirm(__('projects.form.use_ssh'), default: filled($current?->ssh))) {
            $answers['ssh'] = [
                'host' => text(__('console.project_settings.connection.ssh_host'), placeholder: __('projects.form.ssh_host_placeholder'), default: (string) ($current?->ssh['host'] ?? ''), required: true),
                'port' => text(__('console.project_settings.connection.ssh_port'), placeholder: __('projects.form.ssh_port_placeholder'), default: (string) ($current?->ssh['port'] ?? '22')),
                'user' => text(__('projects.form.ssh_user'), placeholder: __('projects.form.ssh_user_placeholder'), default: (string) ($current?->ssh['user'] ?? ''), required: true),
                'id_file' => text(
                    __('projects.form.ssh_id_file'),
                    placeholder: __('projects.form.ssh_id_file_placeholder'),
                    default: (string) ($current?->ssh['id_file'] ?? ''),
                    required: true,
                    hint: __('projects.form.ssh_id_file_description'),
                ),
            ];
        }

        return $answers;
    }
}
