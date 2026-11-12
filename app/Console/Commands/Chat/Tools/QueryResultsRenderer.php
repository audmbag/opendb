<?php

namespace App\Console\Commands\Chat\Tools;

use App\Support\TranslatableMessage;

use function Laravel\Prompts\select;

/**
 * The rows of a query the agent displays: the first ones under the call, then page by page once opened.
 */
class QueryResultsRenderer extends ArtifactRenderer
{
    private const int PREVIEW_ROWS = 10;

    private const int PAGE_ROWS = 25;

    private const string NEXT = 'next';

    private const string PREVIOUS = 'previous';

    private const string SORT = 'sort';

    private const string CLOSE = 'close';

    public function title(array $arguments): string
    {
        return (string) ($arguments['title'] ?? __('ai.artifacts.query_results'));
    }

    public function summary(mixed $result): ?string
    {
        $total = $this->resultData($result)['totalRows'] ?? null;

        return is_int($total) ? trans_choice('chat_cli.tools.rows', $total, ['count' => TranslatableMessage::formatNumber($total)]) : null;
    }

    /**
     * {@inheritdoc}
     */
    protected function lines(array $arguments, mixed $result, int $width, bool $preview): array
    {
        return $this->page($arguments, $width, 1, self::PREVIEW_ROWS);
    }

    /**
     * The user moves through the pages and sorts the rows, until they close the table.
     */
    public function open(array $arguments, mixed $result): void
    {
        $page = 1;
        $sort = null;
        $direction = 'asc';

        $this->terminal->line($this->terminal->theme->bold($this->title($arguments)));

        do {
            $pages = 1;

            $this->terminal->lines($this->guarded(function () use ($arguments, $page, $sort, $direction, &$pages, &$columns): array {
                return $this->page($arguments, $this->terminal->windowWidth(), $page, self::PAGE_ROWS, $sort, $direction, $pages, $columns);
            }));
            $this->terminal->line();

            $action = $this->session->interactive ? $this->nextAction($page, $pages) : self::CLOSE;

            if ($action === self::SORT) {
                $sort = select(__('chat_cli.artifacts.sort_column'), $columns ?? []);
                $direction = select(__('chat_cli.artifacts.sort_direction'), ['asc' => __('chat_cli.artifacts.ascending'), 'desc' => __('chat_cli.artifacts.descending')]);
                $page = 1;
            }

            $page += match ($action) {
                self::NEXT => 1,
                self::PREVIOUS => -1,
                default => 0,
            };
        } while ($action !== self::CLOSE);
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @param  int  $pages  set to the number of pages of the table
     * @param  list<string>|null  $columns  set to the columns of the table
     * @return list<string>
     */
    private function page(array $arguments, int $width, int $page, int $perPage, ?string $sort = null, string $direction = 'asc', int &$pages = 1, ?array &$columns = null): array
    {
        $theme = $this->terminal->theme;
        $data = $this->artifacts->results($this->driver(), $arguments, $page, $perPage, $sort, $direction);

        $columns = $data['columns'];
        $pages = max((int) ceil($data['total'] / $perPage), 1);

        if ($data['rows'] === []) {
            return [$theme->muted(__('ai.artifacts.no_data'))];
        }

        $first = ($page - 1) * $perPage + 1;

        return [
            ...$this->tablePrinter->lines(array_map(strval(...), array_keys($data['rows'][0])), $data['rows'], maxWidth: $width),
            $theme->muted(__('chat_cli.artifacts.rows_shown', [
                'first' => TranslatableMessage::formatNumber($first),
                'last' => TranslatableMessage::formatNumber($first + count($data['rows']) - 1),
                'total' => TranslatableMessage::formatNumber($data['total']),
            ])),
        ];
    }

    private function nextAction(int $page, int $pages): string
    {
        return select(__('chat_cli.artifacts.page', ['page' => $page, 'pages' => $pages]), array_filter([
            self::NEXT => $page < $pages ? __('chat_cli.artifacts.next') : null,
            self::PREVIOUS => $page > 1 ? __('chat_cli.artifacts.previous') : null,
            self::SORT => __('chat_cli.artifacts.sort'),
            self::CLOSE => __('chat_cli.artifacts.close'),
        ]));
    }
}
