<?php

namespace App\Ai\Agents\Tables;

use App\Services\TableBrowser\FilterOperator;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Turn a sentence of the user into the filters of the grid. It writes no SQL: only columns of the table and
 * operators of the browser can come out, and the filters are checked again before being shown.
 */
class TableFilterAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * @param  list<array{name: string, kind: string, db_type: string, nullable: bool, options: ?list<string>, references: ?array{table: string, column: string}}>  $columns
     */
    public function __construct(
        public string $table,
        public array $columns,
        public string $today,
    ) {}

    public function instructions(): Stringable|string
    {
        $columns = implode("\n", array_map(fn (array $column): string => '- '.$column['name'].' : '.$column['kind'].' ('.$column['db_type'].')'
            .($column['nullable'] ? ', peut être NULL' : '')
            .($column['options'] !== null ? ', valeurs possibles : '.implode(', ', $column['options']) : '')
            .($column['references'] !== null ? ", référence {$column['references']['table']}.{$column['references']['column']}" : ''), $this->columns));

        return <<<INSTRUCTIONS
        Tu traduis une demande en français en filtres sur la table « {$this->table} » d'un client de base de données.
        Nous sommes le {$this->today}.

        Colonnes de la table :
        {$columns}

        Opérateurs disponibles :
        - = != > >= < <= : comparaison avec une valeur
        - contains, not_contains, starts_with, ends_with : recherche de texte, sans tenir compte de la casse
        - in : la valeur est une liste séparée par des virgules
        - is_null, is_not_null : sans valeur (mets null)

        Règles :
        - N'utilise que les colonnes et les opérateurs listés. Ne devine pas une colonne qui n'existe pas.
        - Écris les dates au format AAAA-MM-JJ et les dates avec heure au format AAAA-MM-JJ HH:MM:SS.
          Une période devient deux filtres (>= début et < fin) combinés avec match = all.
        - Écris les booléens 1 ou 0.
        - match = all quand toutes les conditions doivent être vraies, any quand une seule suffit. Tu ne peux pas mélanger les deux.
        - Si la demande ne peut pas être exprimée avec ces filtres (jointure, agrégat, calcul), renvoie une liste vide
          et explique pourquoi dans explanation, en proposant de la poser dans le chat.
        - explanation tient en une phrase courte qui décrit le filtre en français, sans répéter les noms techniques des opérateurs.
        INSTRUCTIONS;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'match' => $schema->string()->enum(['all', 'any'])->required(),
            'conditions' => $schema->array()->items($schema->object([
                'column' => $schema->string()->enum(array_column($this->columns, 'name'))->required(),
                'operator' => $schema->string()->enum(FilterOperator::values())->required(),
                'value' => $schema->string()->nullable()->required(),
            ])->withoutAdditionalProperties())->max(10)->required(),
            'explanation' => $schema->string()->required(),
        ];
    }
}
