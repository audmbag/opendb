<?php

use App\Ai\Enums\AgentAccessModeEnum;
use App\Services\Exporter\ExportCompression;
use App\Services\Settings\UserSettings;

it('falls back to the default of every value missing or invalid', function (array $saved) {
    expect(UserSettings::fromArray($saved))->toEqual(new UserSettings);
})->with([
    'nothing saved' => [[]],
    'unknown values' => [['agent' => ['accessMode' => 'admin', 'effort' => 'max', 'maxSteps' => 'many', 'schemaApplyMode' => 'always'], 'export' => ['format' => 'xlsx']]],
    'out of range numbers' => [['agent' => ['maxSteps' => UserSettings::MAX_AGENT_STEPS + 1, 'maxRows' => UserSettings::MIN_AGENT_ROWS - 1]]],
]);

it('replaces the values of one section and keeps the others', function () {
    $settings = new UserSettings(exportCompression: ExportCompression::Tar)
        ->withSection('agent', ['accessMode' => 'read-write', 'maxRows' => 50]);

    expect($settings)
        ->agentAccessMode->toBe(AgentAccessModeEnum::ReadWrite)
        ->agentMaxRows->toBe(50)
        ->agentMaxSteps->toBe(15)
        ->exportCompression->toBe(ExportCompression::Tar);
});
