<?php

declare(strict_types=1);

namespace App\Services\Cloning;

/**
 * Mémoire demandée par le remapping des clés primaires au moteur de clonage, sous une limite de 128 Mo.
 *
 * Le moteur (Engine\Cloning\KeyRemappingService + InMemoryKeyRemappingStore, porté de clonio 1.1.1) garde les
 * correspondances de toutes les tables remappées jusqu'à la fin du clonage, et lit d'un coup les identifiants de
 * chaque table pour les générer : une table de plus de 131 072 lignes dépasse à elle seule la limite. Les coûts
 * ci-dessous ont été mesurés sur clonio 1.1.1 ; les tableaux PHP réservent leurs cases par puissances de deux.
 * CloningRunnerHelper garantit au clonage ces 128 Mo au-dessus de la mémoire déjà utilisée par l'application.
 */
final readonly class RemappingMemoryBudget
{
    const int CLONIO_MEMORY_LIMIT = 128 * 1024 * 1024;

    /**
     * Mémoire occupée par clonio avant le remapping (application, schéma, configuration), avec une marge.
     */
    const int CLONIO_BASE_MEMORY = 56 * 1024 * 1024;

    /**
     * Pic temporaire pendant la génération d'une table : un objet par identifiant lu, puis par case de tableau.
     */
    const int ROW_PEAK_BYTES = 480;

    const int SLOT_PEAK_BYTES = 34;

    /**
     * Correspondance ancienne → nouvelle valeur gardée jusqu'à la fin du clonage, par case de tableau.
     */
    const int SLOT_STORE_BYTES = 72;

    /**
     * @param  int  $transferBytes  Mémoire d'un lot pendant la copie, quand les correspondances sont toutes chargées.
     */
    public function __construct(private int $transferBytes = 0) {}

    /**
     * Garde, dans l'ordre donné, les tables dont le remapping tient encore dans le budget.
     *
     * @param  array<string, int>  $rows  Nombre de lignes par table candidate, par ordre de priorité.
     * @return list<string>
     */
    public function select(array $rows): array
    {
        $selected = [];
        foreach ($rows as $table => $count) {
            if ($this->fits([...$selected, $table => $count])) {
                $selected[$table] = $count;
            }
        }

        return array_map('strval', array_keys($selected));
    }

    /**
     * @param  array<string, int>  $rows  Nombre de lignes par table remappée.
     */
    public function fits(array $rows): bool
    {
        $store = 0;
        $peak = $this->transferBytes;
        foreach ($rows as $count) {
            $store += self::SLOT_STORE_BYTES * self::slots($count);
            $peak = max($peak, self::ROW_PEAK_BYTES * $count + self::SLOT_PEAK_BYTES * self::slots($count));
        }

        return self::CLONIO_BASE_MEMORY + $store + $peak <= self::CLONIO_MEMORY_LIMIT;
    }

    /**
     * Cases réservées par PHP pour un tableau de $count éléments.
     */
    private static function slots(int $count): int
    {
        $slots = 8;
        while ($slots < $count) {
            $slots <<= 1;
        }

        return $slots;
    }
}
