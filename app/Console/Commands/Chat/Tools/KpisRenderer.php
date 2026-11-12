<?php

namespace App\Console\Commands\Chat\Tools;

use App\Support\TranslatableMessage;
use Carbon\CarbonInterval;

/**
 * The metric cards the agent displays, one line for each: its label, then its value.
 */
class KpisRenderer extends ArtifactRenderer
{
    public function title(array $arguments): string
    {
        return __('ai.artifacts.kpis');
    }

    /**
     * {@inheritdoc}
     */
    public function call(array $arguments): array
    {
        return [$this->terminal->theme->bold($this->title($arguments))];
    }

    /**
     * {@inheritdoc}
     */
    protected function lines(array $arguments, mixed $result, int $width, bool $preview): array
    {
        $theme = $this->terminal->theme;
        $cards = $this->artifacts->kpis($this->driver(), $arguments);
        $labelWidth = max([0, ...array_map(fn (array $card): int => mb_strwidth($card['label']), $cards)]);

        return array_map(fn (array $card): string => $theme->muted(mb_str_pad($card['label'], $labelWidth)).'  '.(
            $card['error'] === null ? $theme->bold($this->value($card)) : $theme->danger($card['error'])
        ), $cards);
    }

    /**
     * @param  array{format: string, currency: ?string, unit: ?string, value: mixed}  $card
     */
    private function value(array $card): string
    {
        $value = $card['value'];

        if (! is_numeric($value)) {
            return $value === null ? 'NULL' : (string) $value;
        }

        $number = TranslatableMessage::formatNumber($value + 0, is_float($value + 0) ? 'decimal' : 'integer');

        return trim(match ($card['format']) {
            'currency' => TranslatableMessage::formatNumber((float) $value, 'decimal').' '.$card['currency'],
            'percent' => $number.' %',
            'duration' => CarbonInterval::seconds((int) round((float) $value))->cascade()->forHumans(short: true),
            default => $number,
        }.' '.($card['unit'] ?? ''));
    }
}
