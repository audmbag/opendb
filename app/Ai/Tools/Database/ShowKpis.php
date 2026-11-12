<?php

namespace App\Ai\Tools\Database;

use App\Database\Contracts\DriverContract;
use App\Database\QueryBindings;
use App\Database\SelectQuery;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use RuntimeException;
use Stringable;

class ShowKpis extends BaseTool implements Tool
{
    public const int MAX_KPIS = 6;

    public const array FORMATS = ['number', 'currency', 'percent', 'duration'];

    public function __construct(
        public DriverContract $driver,
    ) {}

    public function description(): Stringable|string
    {
        return 'Displays metric cards (KPIs) in the interface, as an artifact. Each card runs its own SELECT query, '
            .'which must return ONE single value (one row, one column, e.g. `SELECT COUNT(*) FROM orders WHERE '
            .'created_at >= :since`). This tool does not return the values to you. With `format`, percent expects '
            .'a value already in percent (e.g. 12.5) and duration a value in seconds. With the currency format, '
            .'`currency` is required: the ISO 4217 code of the currency (EUR, USD, CHF, XOF…), as given by the '
            .'user or by the data (a `currency` column, an explicit column name). Never assume a currency: if it '
            .'is unknown, ask the user before calling this tool.';
    }

    public function handle(Request $request): Stringable|string
    {
        return $this->artifactOutput(function () use ($request): array {
            $kpis = array_values((array) ($request['kpis'] ?? []));

            if ($kpis === [] || count($kpis) > self::MAX_KPIS) {
                throw new RuntimeException('The `kpis` parameter must hold between 1 and '.self::MAX_KPIS.' cards.');
            }

            foreach ($kpis as $index => $kpi) {
                if (blank($kpi['label'] ?? null)) {
                    throw new RuntimeException("Card #{$index} has no `label`.");
                }

                if (($kpi['format'] ?? null) === 'currency' && self::currencyOf($kpi) === null) {
                    throw new RuntimeException("The card \"{$kpi['label']}\" uses the currency format: give its currency in `currency` (ISO 4217 code, e.g. EUR, USD). If you do not know it, ask the user.");
                }

                new SelectQuery($this->driver, (string) ($kpi['query'] ?? ''), QueryBindings::from($kpi['bindings'] ?? null));
            }

            return [
                'labels' => array_column($kpis, 'label'),
                'count' => count($kpis),
            ];
        });
    }

    /**
     * The ISO 4217 code of the currency of a card, or null when it is missing or malformed.
     *
     * @param  array<string, mixed>  $kpi
     */
    public static function currencyOf(array $kpi): ?string
    {
        $currency = strtoupper(trim((string) ($kpi['currency'] ?? '')));

        return preg_match('/^[A-Z]{3}$/', $currency) === 1 ? $currency : null;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'kpis' => $schema->array()
                ->items($schema->object(fn ($schema) => [
                    'label' => $schema->string()->required()->description('Short label of the metric (e.g. "Revenue").'),
                    ...$this->selectQuerySchema($schema),
                    'format' => $schema->string()->enum(self::FORMATS)->required()->description('Display format of the value.'),
                    'currency' => $schema->string()->nullable()->description('ISO 4217 code of the currency (e.g. "EUR", "USD", "CHF"), required with the currency format.'),
                    'unit' => $schema->string()->nullable()->description('Optional suffix displayed after the value (e.g. "orders").'),
                ]))
                ->min(1)
                ->max(self::MAX_KPIS)
                ->required()
                ->description('The cards to display, in order.'),
        ];
    }
}
