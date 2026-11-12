<?php

namespace App\Console\Commands\Settings\Global;

use App\Console\Commands\Settings\UserSection;
use App\Http\Requests\Settings\ExportSettingsRequest;
use App\Models\User;
use App\Services\Exporter\ExportCompression;
use App\Services\Exporter\ExportFormat;
use App\Services\Settings\SettingsManager;

use function Laravel\Prompts\info;
use function Laravel\Prompts\select;

/**
 * The format and the compression offered by default for every export.
 */
class ExportSection extends UserSection
{
    public function __construct(protected SettingsManager $settings) {}

    public function label(): string
    {
        return __('settings.nav.exports');
    }

    public function run(User $user): void
    {
        $current = $user->settings;

        $request = $this->validate(ExportSettingsRequest::class, [
            'format' => select(
                __('settings.export.format'),
                $this->enumOptions(ExportFormat::cases(), 'exports.formats'),
                default: $current->exportFormat->value,
                hint: __('settings.export.format_description'),
            ),
            'compression' => select(
                __('settings.export.compression'),
                $this->enumOptions(ExportCompression::cases(), 'exports.compressions'),
                default: $current->exportCompression->value,
                hint: __('settings.export.compression_description'),
            ),
        ]);

        if ($request === null) {
            return;
        }

        $this->settings->update($user, 'export', $request->validated());

        info(__('settings.messages.export_saved'));
    }
}
