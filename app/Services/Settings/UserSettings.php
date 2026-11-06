<?php

namespace App\Services\Settings;

use App\Ai\Enums\AgentAccessModeEnum;
use App\Ai\Enums\AgentEffortEnum;
use App\Services\Exporter\ExportCompression;
use App\Services\Exporter\ExportFormat;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * The settings of a user, read by the agent and by the interface. A missing or invalid value falls back to its default,
 * so settings saved by an older version keep working.
 *
 * @implements Arrayable<string, array<string, mixed>>
 */
final readonly class UserSettings implements Arrayable, JsonSerializable
{
    public const int MIN_AGENT_STEPS = 3;

    public const int MAX_AGENT_STEPS = 50;

    public const int MIN_AGENT_ROWS = 10;

    public const int MAX_AGENT_ROWS = 1000;

    public function __construct(
        public AgentAccessModeEnum $agentAccessMode = AgentAccessModeEnum::ReadOnly,
        public AgentEffortEnum $agentEffort = AgentEffortEnum::Default,
        public int $agentMaxSteps = 15,
        public int $agentMaxRows = 200,
        public ExportFormat $exportFormat = ExportFormat::Sql,
        public ExportCompression $exportCompression = ExportCompression::Zip,
    ) {}

    /**
     * @param  array<string, mixed>  $settings  the settings as saved, grouped by section
     */
    public static function fromArray(array $settings): self
    {
        $defaults = new self;
        $agent = (array) ($settings['agent'] ?? []);
        $export = (array) ($settings['export'] ?? []);

        return new self(
            agentAccessMode: AgentAccessModeEnum::tryFrom((string) ($agent['accessMode'] ?? '')) ?? $defaults->agentAccessMode,
            agentEffort: AgentEffortEnum::tryFrom((string) ($agent['effort'] ?? '')) ?? $defaults->agentEffort,
            agentMaxSteps: self::between($agent['maxSteps'] ?? null, self::MIN_AGENT_STEPS, self::MAX_AGENT_STEPS, $defaults->agentMaxSteps),
            agentMaxRows: self::between($agent['maxRows'] ?? null, self::MIN_AGENT_ROWS, self::MAX_AGENT_ROWS, $defaults->agentMaxRows),
            exportFormat: ExportFormat::tryFrom((string) ($export['format'] ?? '')) ?? $defaults->exportFormat,
            exportCompression: ExportCompression::tryFrom((string) ($export['compression'] ?? '')) ?? $defaults->exportCompression,
        );
    }

    /**
     * These settings with the values of one section replaced.
     *
     * @param  'agent'|'export'  $section
     * @param  array<string, mixed>  $values
     */
    public function withSection(string $section, array $values): self
    {
        $settings = $this->toArray();

        return self::fromArray([...$settings, $section => [...$settings[$section], ...$values]]);
    }

    /**
     * @return array{agent: array{accessMode: string, effort: string, maxSteps: int, maxRows: int}, export: array{format: string, compression: string}}
     */
    public function toArray(): array
    {
        return [
            'agent' => [
                'accessMode' => $this->agentAccessMode->value,
                'effort' => $this->agentEffort->value,
                'maxSteps' => $this->agentMaxSteps,
                'maxRows' => $this->agentMaxRows,
            ],
            'export' => [
                'format' => $this->exportFormat->value,
                'compression' => $this->exportCompression->value,
            ],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    private static function between(mixed $value, int $min, int $max, int $default): int
    {
        return is_numeric($value) && (int) $value >= $min && (int) $value <= $max ? (int) $value : $default;
    }
}
