<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\AuthenticatesUser;
use App\Repository\ProviderRepository;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('ai:models
    {provider? : Limit output to a single provider (e.g. openai)}
    {--name= : The username to log in with; the password is always asked}
    {--json : Output raw JSON instead of a table}')]
#[Description('List the AI models the user activated on their connected providers')]
class ListAiModels extends Command
{
    use AuthenticatesUser;

    public function handle(ProviderRepository $providerRepository): int
    {
        if ($this->authenticateUser() === null) {
            return self::FAILURE;
        }

        $models = $providerRepository->activeModels()
            ->when(
                $this->argument('provider') !== null,
                fn ($models) => $models->where('provider', $this->argument('provider'))->values(),
            );

        if ($models->isEmpty()) {
            $this->warn('Aucun modèle actif : connectez un fournisseur et activez un modèle dans les paramètres.');

            return self::SUCCESS;
        }

        if ($this->option('json')) {
            $this->line($models->toJson(JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->table(
            ['Provider', 'Model ID', 'Name', 'Context'],
            $models->map(fn (array $model): array => [
                $model['provider'],
                $model['id'],
                $model['label'],
                $model['context_window'] !== null ? number_format($model['context_window']) : '—',
            ])->all(),
        );

        return self::SUCCESS;
    }
}
