<?php

namespace App\Ai\Agents\Tables;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Suggest the indexes that would speed up the view of a table, from the plan of its query. The indexes are never created.
 */
class TableIndexAdvisorAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function __construct(
        public string $table,
        public string $driver,
    ) {}

    public function instructions(): Stringable|string
    {
        return <<<INSTRUCTIONS
        Tu es un expert en performance de bases de données {$this->driver}. On te donne la requête qui affiche une vue de la table « {$this->table} »
        (ses filtres et son tri), le résumé de son plan d'exécution, les alertes détectées et les index qui existent déjà.

        Réponds en français :
        - indexes : les index qui accéléreraient vraiment cette requête, du plus utile au moins utile, trois au plus.
          statement est l'instruction CREATE INDEX complète, valide pour {$this->driver}, avec un nom explicite
          et ses mots-clés en MAJUSCULES.
          N'en propose pas un qui doublonne un index existant ou qui commence par les mêmes colonnes. Mets en tête de l'index
          les colonnes filtrées par égalité, puis celles des comparaisons, puis celles du tri.
          impact vaut high quand le plan parcourt toute une grande table ou trie sans index, medium ou low sinon.
          reason explique en une phrase ce que l'index évite dans le plan.
        - note : une phrase sur le plan actuel. Si la requête est déjà efficace ou que la table est trop petite pour qu'un index compte,
          dis-le et renvoie une liste d'index vide.
        INSTRUCTIONS;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'indexes' => $schema->array()->items($schema->object([
                'statement' => $schema->string()->required(),
                'columns' => $schema->array()->items($schema->string())->required(),
                'reason' => $schema->string()->required(),
                'impact' => $schema->string()->enum(['high', 'medium', 'low'])->required(),
            ])->withoutAdditionalProperties())->max(3)->required(),
            'note' => $schema->string()->required(),
        ];
    }
}
