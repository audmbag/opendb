<?php

declare(strict_types=1);

namespace App\Services\Installation;

/**
 * Passerelle entre l'application et son installation chez l'utilisateur : au premier lancement d'une version, la base
 * est sauvegardée, migrée et complétée avant la première requête ou commande qui s'en sert.
 */
final readonly class InstallationManager
{
    public function __construct(
        private ReleaseMarker $marker,
        private DatabaseBackup $backup,
        private SchemaUpgrade $schema,
        private InitialData $data,
        private StaleCaches $caches,
    ) {}

    public function upgradeIfNeeded(): void
    {
        if ($this->marker->isCurrent()) {
            return;
        }

        $this->marker->exclusively(function (): void {
            if ($this->marker->isCurrent()) {
                return;
            }

            $this->backup->save();
            $this->schema->run();
            $this->data->seed();
            $this->caches->clear();
            $this->marker->record();
        });
    }
}
