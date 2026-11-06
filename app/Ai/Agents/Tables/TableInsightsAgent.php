<?php

namespace App\Ai\Agents\Tables;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Explain what a table holds and point at what looks wrong in its data, from its structure and the profile of its columns.
 */
class TableInsightsAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * @param  list<string>  $columns  the names of the columns of the table
     */
    public function __construct(
        public string $table,
        public array $columns,
    ) {}

    public function instructions(): Stringable|string
    {
        return <<<INSTRUCTIONS
        Tu es un analyste de données. On te donne la structure de la table « {$this->table} » et le profil de ses colonnes,
        calculé sur un échantillon de ses lignes : taux de NULL, nombre de valeurs distinctes, minimum, maximum, longueurs et valeurs fréquentes.

        Réponds en français, de façon concrète et brève :
        - summary : deux ou trois phrases sur ce que contient la table et son rôle dans la base, d'après ses colonnes et ses relations.
        - columns : le sens de chaque colonne dont le nom n'est pas évident, en quelques mots. Laisse de côté les colonnes évidentes comme id, created_at ou updated_at.
        - anomalies : ce qui mérite l'attention dans les données, avec les chiffres du profil. Par exemple, une colonne presque toujours NULL,
          une colonne unique en pratique sans index unique, des valeurs hors de l'ordre de grandeur attendu, une colonne qui ne sert jamais,
          ou des formats mélangés. N'invente rien que le profil ne montre pas. Si rien n'est à signaler, renvoie une liste vide.
          severity vaut warning pour un problème probable, info pour une simple remarque.
        - questions : deux ou trois questions précises qu'il serait utile de poser ensuite sur cette table à l'assistant SQL.
        INSTRUCTIONS;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'summary' => $schema->string()->required(),
            'columns' => $schema->array()->items($schema->object([
                'name' => $schema->string()->enum($this->columns)->required(),
                'meaning' => $schema->string()->required(),
            ])->withoutAdditionalProperties())->required(),
            'anomalies' => $schema->array()->items($schema->object([
                'column' => $schema->string()->enum($this->columns)->nullable()->required(),
                'issue' => $schema->string()->required(),
                'severity' => $schema->string()->enum(['info', 'warning'])->required(),
            ])->withoutAdditionalProperties())->required(),
            'questions' => $schema->array()->items($schema->string())->max(3)->required(),
        ];
    }
}
