<?php

namespace App\Ai\Tools\Database;

use App\Database\Contracts\DriverContract;
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
        return 'Affiche des cartes de métriques (KPI) dans l\'interface, sous forme d\'artefact. Chaque carte exécute '
            .'sa propre requête SELECT qui doit renvoyer UNE seule valeur (une ligne, une colonne, ex. '
            .'`SELECT COUNT(*) FROM orders WHERE created_at >= :since`). Ce tool ne te renvoie pas les valeurs. '
            .'Avec `format`, percent attend une valeur déjà en pourcentage (ex. 12.5) et duration une valeur en secondes. Avec le format '
            .'currency, `currency` est obligatoire : le code ISO 4217 de la devise (EUR, USD, CHF, XOF…), tel que précisé par '
            .'l\'utilisateur ou par les données (colonne `currency`, nom de colonne explicite). Ne suppose jamais une devise : '
            .'si elle est inconnue, demande-la à l\'utilisateur avant d\'appeler ce tool.';
    }

    public function handle(Request $request): Stringable|string
    {
        return $this->artifactOutput(function () use ($request): array {
            $kpis = array_values((array) ($request['kpis'] ?? []));

            if ($kpis === [] || count($kpis) > self::MAX_KPIS) {
                throw new RuntimeException('Le paramètre `kpis` doit contenir entre 1 et '.self::MAX_KPIS.' cartes.');
            }

            foreach ($kpis as $index => $kpi) {
                if (blank($kpi['label'] ?? null)) {
                    throw new RuntimeException("La carte n°{$index} n'a pas de `label`.");
                }

                if (($kpi['format'] ?? null) === 'currency' && self::currencyOf($kpi) === null) {
                    throw new RuntimeException("La carte « {$kpi['label']} » est au format currency : précise sa devise dans `currency` (code ISO 4217, ex. EUR, USD). Si tu ne la connais pas, demande-la à l'utilisateur.");
                }

                new SelectQuery($this->driver, (string) ($kpi['query'] ?? ''), $kpi['bindings'] ?? []);
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
                    'label' => $schema->string()->required()->description('Libellé court de la métrique (ex. "Chiffre d\'affaires").'),
                    ...$this->selectQuerySchema($schema),
                    'format' => $schema->string()->enum(self::FORMATS)->required()->description('Format d\'affichage de la valeur.'),
                    'currency' => $schema->string()->nullable()->description('Code ISO 4217 de la devise (ex. "EUR", "USD", "CHF"), obligatoire avec le format currency.'),
                    'unit' => $schema->string()->nullable()->description('Suffixe optionnel affiché après la valeur (ex. "commandes").'),
                ]))
                ->min(1)
                ->max(self::MAX_KPIS)
                ->required()
                ->description('Les cartes à afficher, dans l\'ordre.'),
        ];
    }
}
