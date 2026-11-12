<?php

namespace App\Console\Commands\Chat\Tools;

/**
 * The card of a record the agent displays: its columns, the records it references and those referencing it.
 */
class RecordRenderer extends ArtifactRenderer
{
    private const int PREVIEW_COLUMNS = 12;

    public function title(array $arguments): string
    {
        return trim(($arguments['tableName'] ?? '').' '.$this->key((array) ($arguments['key'] ?? [])));
    }

    /**
     * {@inheritdoc}
     */
    protected function lines(array $arguments, mixed $result, int $width, bool $preview): array
    {
        $theme = $this->terminal->theme;
        $record = $this->artifacts->record($this->driver(), $arguments);
        $values = $preview ? array_slice($record['values'], 0, self::PREVIEW_COLUMNS, preserve_keys: true) : $record['values'];
        $hidden = count($record['values']) - count($values);
        $nameWidth = max([0, ...array_map(mb_strwidth(...), array_keys($values))]);

        $lines = [];

        foreach ($values as $name => $value) {
            $lines[] = $theme->muted(mb_str_pad((string) $name, $nameWidth)).'  '.($value === null ? $theme->muted('NULL') : $this->text($value, $preview));
        }

        if ($hidden > 0) {
            $lines[] = $theme->muted(trans_choice('chat_cli.artifacts.more_columns', $hidden));
        }

        foreach ($record['outgoing'] as $reference) {
            $lines[] = $theme->accent('→').' '.$reference['table'].' '.$theme->muted($this->key($reference['key']));
        }

        foreach ($record['incoming'] as $reference) {
            $lines[] = $theme->accent('←').' '.$reference['table'].' '.$theme->muted('×'.$reference['count']);
        }

        return $lines;
    }

    private function text(mixed $value, bool $preview): string
    {
        $text = is_scalar($value) ? (string) $value : (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $preview ? $this->short($text) : str_replace(["\r\n", "\n"], ' ', $text);
    }

    /**
     * @param  array<string, mixed>  $key
     */
    private function key(array $key): string
    {
        return implode(', ', array_map(fn (mixed $value, string $column): string => "{$column} = ".$this->short($value), $key, array_keys($key)));
    }
}
