<?php

namespace App\Services\Usage;

use App\Ai\Enums\AiFeatureEnum;
use App\Models\Provider;
use App\Models\UsageRecord;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * The usage of the models over a period, summed in the database: by provider and model, by feature, and over time.
 *
 * @phpstan-type Totals array{requests: int, runs: int, input_tokens: int, output_tokens: int, cache_read_input_tokens: int, cache_write_input_tokens: int, reasoning_tokens: int, failures: int, average_duration: ?int}
 */
class UsageStatistics
{
    /**
     * Above this many days, the timeline shows one point per month instead of one per day.
     */
    protected const int MAX_DAILY_POINTS = 120;

    /**
     * @param  ?int  $days  the period, ending today, or null for the whole history
     * @return array<string, mixed>
     */
    public function summarize(?int $days): array
    {
        $today = CarbonImmutable::today();
        $from = $days === null ? null : $today->subDays($days - 1);

        $models = $this->modelRows($from);
        $first = $models->min('first_used_at');
        $timelineStart = $from ?? ($first === null ? $today : CarbonImmutable::parse($first)->startOfDay());

        return [
            'period' => [
                'days' => $days,
                'from' => $timelineStart->toDateString(),
                'to' => $today->toDateString(),
            ],
            'totals' => [
                ...$this->sum($models),
                'providers' => $models->pluck('provider')->unique()->count(),
                'models' => $models->count(),
            ],
            'previous' => $from === null ? null : $this->sum($this->modelRows($from->subDays($days), $from)),
            'providers' => $this->providers($models),
            'features' => $this->features($from),
            'timeline' => $this->timeline($from, $timelineStart, $today),
        ];
    }

    /**
     * The usage of each model of each provider, in the order of the tokens they used.
     *
     * @return Collection<int, array<string, mixed>>
     */
    protected function modelRows(?CarbonImmutable $from, ?CarbonImmutable $until = null): Collection
    {
        return $this->query($from, $until)
            ->select(['provider', 'model'])
            ->selectRaw('COUNT(*) AS requests')
            ->selectRaw('COUNT(DISTINCT invocation_id) AS runs')
            ->selectRaw('SUM(input_tokens) AS input_tokens')
            ->selectRaw('SUM(output_tokens) AS output_tokens')
            ->selectRaw('SUM(cache_read_input_tokens) AS cache_read_input_tokens')
            ->selectRaw('SUM(cache_write_input_tokens) AS cache_write_input_tokens')
            ->selectRaw('SUM(reasoning_tokens) AS reasoning_tokens')
            ->selectRaw('SUM(CASE WHEN failed THEN 1 ELSE 0 END) AS failures')
            ->selectRaw('SUM(CASE WHEN NOT failed THEN duration END) AS duration_sum')
            ->selectRaw('COUNT(CASE WHEN NOT failed THEN duration END) AS timed_requests')
            ->selectRaw('MIN(created_at) AS first_used_at')
            ->selectRaw('MAX(created_at) AS last_used_at')
            ->groupBy(['provider', 'model'])
            ->toBase()
            ->get()
            ->map(fn (object $row): array => [
                'provider' => $row->provider,
                'model' => $row->model,
                'requests' => (int) $row->requests,
                'runs' => (int) $row->runs,
                'input_tokens' => (int) $row->input_tokens,
                'output_tokens' => (int) $row->output_tokens,
                'cache_read_input_tokens' => (int) $row->cache_read_input_tokens,
                'cache_write_input_tokens' => (int) $row->cache_write_input_tokens,
                'reasoning_tokens' => (int) $row->reasoning_tokens,
                'failures' => (int) $row->failures,
                'duration_sum' => (int) $row->duration_sum,
                'timed_requests' => (int) $row->timed_requests,
                'first_used_at' => CarbonImmutable::parse($row->first_used_at)->toIso8601String(),
                'last_used_at' => CarbonImmutable::parse($row->last_used_at)->toIso8601String(),
            ])
            ->sortByDesc(fn (array $row): int => $row['input_tokens'] + $row['output_tokens'])
            ->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Totals
     */
    protected function sum(Collection $rows): array
    {
        $timedRequests = $rows->sum('timed_requests');

        return [
            'requests' => $rows->sum('requests'),
            'runs' => $rows->sum('runs'),
            'input_tokens' => $rows->sum('input_tokens'),
            'output_tokens' => $rows->sum('output_tokens'),
            'cache_read_input_tokens' => $rows->sum('cache_read_input_tokens'),
            'cache_write_input_tokens' => $rows->sum('cache_write_input_tokens'),
            'reasoning_tokens' => $rows->sum('reasoning_tokens'),
            'failures' => $rows->sum('failures'),
            'average_duration' => $timedRequests > 0 ? (int) round($rows->sum('duration_sum') / $timedRequests) : null,
        ];
    }

    /**
     * The providers used, each with its name, its icon and the usage of its models.
     *
     * @param  Collection<int, array<string, mixed>>  $models
     * @return list<array<string, mixed>>
     */
    protected function providers(Collection $models): array
    {
        $providers = Provider::query()->whereIn('id', $models->pluck('provider')->unique())->get()->keyBy(fn (Provider $provider) => $provider->name());

        return $models
            ->groupBy('provider')
            ->map(function (Collection $rows, string $id) use ($providers): array {
                $provider = $providers->get($id);
                $labels = collect($provider->models ?? [])->pluck('label', 'id');

                return [
                    'id' => $id,
                    'label' => $provider->label ?? Str::headline($id),
                    'icon' => $provider->icon ?? "/assets/providers/{$id}.svg",
                    ...$this->sum($rows),
                    'last_used_at' => $rows->max('last_used_at'),
                    'models' => $rows
                        ->map(fn (array $row): array => [
                            'id' => $row['model'],
                            'label' => $labels->get($row['model']) ?? $row['model'],
                            ...$this->sum(collect([$row])),
                            'last_used_at' => $row['last_used_at'],
                        ])
                        ->values()
                        ->all(),
                ];
            })
            ->sortByDesc(fn (array $provider): int => $provider['input_tokens'] + $provider['output_tokens'])
            ->values()
            ->all();
    }

    /**
     * The usage of each feature of the application, in the order of the tokens they used.
     *
     * @return list<array{id: string, feature: string, label: string, requests: int, tokens: int}>
     */
    protected function features(?CarbonImmutable $from): array
    {
        return $this->query($from)
            ->select('feature')
            ->selectRaw('COUNT(*) AS requests')
            ->selectRaw('SUM(input_tokens + output_tokens) AS tokens')
            ->groupBy('feature')
            ->toBase()
            ->get()
            ->map(fn (object $row): array => [
                'id' => $row->feature,
                'feature' => (AiFeatureEnum::tryFrom($row->feature) ?? AiFeatureEnum::Other)->value,
                'requests' => (int) $row->requests,
                'tokens' => (int) $row->tokens,
            ])
            ->sortByDesc('tokens')
            ->values()
            ->all();
    }

    /**
     * The tokens used by each provider on each day of the period, or each month of a long one, without gaps.
     *
     * @return array{bucket: 'day'|'month', points: list<array{date: string, total: int, providers: array<string, int>}>}
     */
    protected function timeline(?CarbonImmutable $from, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $monthly = $start->diffInDays($end) + 1 > self::MAX_DAILY_POINTS;
        $bucketOf = fn (string $day): string => $monthly ? substr($day, 0, 7) : $day;

        $points = collect(CarbonPeriod::create($monthly ? $start->startOfMonth() : $start, $monthly ? '1 month' : '1 day', $end))
            ->mapWithKeys(fn ($date): array => [$bucketOf($date->toDateString()) => ['total' => 0, 'providers' => []]])
            ->all();

        $this->query($from)
            ->selectRaw('DATE(created_at) AS day')
            ->addSelect('provider')
            ->selectRaw('SUM(input_tokens + output_tokens) AS tokens')
            ->groupByRaw('DATE(created_at), provider')
            ->toBase()
            ->get()
            ->each(function (object $row) use (&$points, $bucketOf): void {
                $bucket = $bucketOf((string) $row->day);

                if (! isset($points[$bucket])) {
                    return;
                }

                $points[$bucket]['total'] += (int) $row->tokens;
                $points[$bucket]['providers'][$row->provider] = ($points[$bucket]['providers'][$row->provider] ?? 0) + (int) $row->tokens;
            });

        return [
            'bucket' => $monthly ? 'month' : 'day',
            'points' => collect($points)
                ->map(fn (array $point, string $date): array => ['date' => $date, ...$point])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return Builder<UsageRecord>
     */
    protected function query(?CarbonImmutable $from, ?CarbonImmutable $until = null): Builder
    {
        return UsageRecord::query()
            ->when($from !== null, fn (Builder $query) => $query->where('created_at', '>=', $from))
            ->when($until !== null, fn (Builder $query) => $query->where('created_at', '<', $until));
    }
}
