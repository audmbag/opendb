<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\AuthenticatesUser;
use App\Console\Commands\Concerns\TranslatesCommand;
use App\Repository\ProviderRepository;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Number;

#[Signature('ai:models
    {provider? : console.commands.ai_models.provider}
    {--name= : console.options.name}
    {--json : console.commands.ai_models.json}')]
#[Description('console.commands.ai_models.description')]
class ListAiModels extends Command
{
    use AuthenticatesUser, TranslatesCommand;

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
            $this->warn(__('console.no_model'));

            return self::SUCCESS;
        }

        if ($this->option('json')) {
            $this->line($models->toJson(JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->table(
            [__('console.models.provider'), __('console.models.id'), __('console.models.name'), __('console.models.context')],
            $models->map(fn (array $model): array => [
                $model['provider'],
                $model['id'],
                $model['label'],
                $model['context_window'] !== null ? Number::format($model['context_window'], locale: app()->getLocale()) : '—',
            ])->all(),
        );

        return self::SUCCESS;
    }
}
