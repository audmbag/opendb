<?php

use App\Console\Commands\Chat\MessagePrompt;
use App\Console\Commands\Chat\Output\CodeHighlighter;
use App\Console\Commands\Chat\Output\MarkdownPrinter;
use App\Console\Commands\Chat\Output\SqlHighlighter;
use App\Console\Commands\Chat\Output\StreamingText;
use App\Console\Commands\Chat\Output\TablePrinter;
use App\Console\Commands\Chat\Output\Theme;
use Laravel\Prompts\Key;
use Laravel\Prompts\Prompt;

function markdownPrinter(bool $decorated = false): MarkdownPrinter
{
    $theme = new Theme($decorated);

    return new MarkdownPrinter($theme, new CodeHighlighter($theme, new SqlHighlighter($theme)), new TablePrinter($theme));
}

it('writes Markdown without its marks when the output has no colors', function () {
    $lines = markdownPrinter()->lines("# Clients\n\nIl y a **trois** clients, dont `Ada`.\n\n1. un\n2. deux\n\n> à vérifier", 80);

    expect($lines)->toBe([
        'Clients',
        '',
        'Il y a trois clients, dont Ada.',
        '',
        '1. un',
        '2. deux',
        '',
        '│ à vérifier',
    ]);
});

it('styles Markdown for a terminal with colors', function () {
    $lines = markdownPrinter(decorated: true)->lines('Il y a **trois** clients et *deux* ~~villes~~.', 80);

    expect($lines)->toBe(["Il y a \e[1mtrois\e[22m clients et \e[3mdeux\e[23m \e[9mvilles\e[29m."]);
});

it('breaks a paragraph at the width of the terminal, whatever its styles', function () {
    $lines = markdownPrinter(decorated: true)->lines('un **deux** trois quatre cinq six', 20);

    expect($lines)->toBe(["un \e[1mdeux\e[22m trois quatre", 'cinq six']);
});

it('draws a Markdown table with its columns aligned', function () {
    $lines = markdownPrinter()->lines("| Nom | Total |\n|---|--:|\n| Ada | 200 |\n| Grace | 50 |", 80);

    expect($lines)->toBe([
        '┌───────┬───────┐',
        '│ Nom   │ Total │',
        '├───────┼───────┤',
        '│ Ada   │   200 │',
        '│ Grace │    50 │',
        '└───────┴───────┘',
    ]);
});

it('colors the SQL of a block of code and leaves it ready to be copied', function () {
    $keyword = fn (string $word): string => "\e[1m\e[38;5;39m{$word}\e[39m\e[22m";
    $lines = markdownPrinter(decorated: true)->lines("```sql\nSELECT name FROM customers WHERE id = 1\n```", 80);

    expect($lines)->toBe(["    {$keyword('SELECT')} name {$keyword('FROM')} customers {$keyword('WHERE')} id = \e[38;5;215m1\e[39m"]);
});

it('shows where a link leads when the terminal cannot open it', function () {
    expect(markdownPrinter()->lines('Voir [la doc](https://opendb.test/doc).', 80))->toBe(['Voir la doc (https://opendb.test/doc).']);
});

it('does not color a keyword written inside a text or a comment', function () {
    $sql = new SqlHighlighter(new Theme(decorated: true))->highlight("SELECT 'from here' -- where");

    expect($sql)->toBe("\e[1m\e[38;5;39mSELECT\e[39m\e[22m \e[38;5;114m'from here'\e[39m \e[2m\e[3m-- where\e[23m\e[22m");
});

it('hands over each block of a streamed answer once it is complete', function () {
    $text = new StreamingText;

    expect($text->push('Voici la '))->toBe([])
        ->and($text->push("réponse.\n\n- un\n"))->toBe(['Voici la réponse.'])
        ->and($text->push("- deux\n\nFin"))->toBe(["- un\n- deux"])
        ->and($text->flush())->toBe('Fin')
        ->and($text->flush())->toBeNull();
});

it('keeps a block of code whole, with its blank lines', function () {
    $text = new StreamingText;

    expect($text->push("```sql\nSELECT 1;\n\nSELECT 2;\n```\n\nEnsuite"))->toBe(["```sql\nSELECT 1;\n\nSELECT 2;\n```"]);
});

it('puts each clause of a long query on its own line', function () {
    $sql = new SqlHighlighter(new Theme(decorated: false))->format(
        "SELECT o.id, LEFT(c.name, 2) FROM orders o LEFT JOIN customers c ON c.id = o.customer_id WHERE o.note = 'from x AND y' AND o.total BETWEEN 1 AND 9 OR o.id IN (SELECT id FROM t WHERE a = 1 AND b = 2) ORDER BY o.id LIMIT 10"
    );

    expect(explode("\n", $sql))->toBe([
        'SELECT o.id, LEFT(c.name, 2)',
        'FROM orders o',
        'LEFT JOIN customers c ON c.id = o.customer_id',
        "WHERE o.note = 'from x AND y'",
        '  AND o.total BETWEEN 1 AND 9',
        '  OR o.id IN (SELECT id FROM t WHERE a = 1 AND b = 2)',
        'ORDER BY o.id',
        'LIMIT 10',
    ]);
});

it('leaves a short query, or one already laid out, as it was written', function (string $sql) {
    expect(new SqlHighlighter(new Theme(decorated: false))->format($sql))->toBe($sql);
})->with([
    'short' => 'SELECT * FROM customers WHERE id = 1',
    'laid out' => "SELECT id, name, email, created_at, updated_at\n    FROM customers WHERE id = 1 AND name = 'x' AND email IS NOT NULL",
]);

it('brings back the messages already sent with the arrows', function (array $keys, string $expected) {
    Prompt::fake($keys);

    $message = new MessagePrompt('Message', fn (): array => [])->withHistory(['Premier', 'Second'])->prompt();

    expect($message)->toBe($expected);
})->with([
    'the last one' => [[Key::UP, Key::ENTER], 'Second'],
    'an older one' => [[Key::UP, Key::UP, Key::UP, Key::ENTER], 'Premier'],
    'back to what was being written' => [['B', 'r', Key::UP, Key::UP, Key::DOWN, Key::DOWN, Key::ENTER], 'Br'],
])->skipOnWindows('Laravel Prompts draws no prompt on Windows: the chat asks through its fallback, without history.');

it('breaks a styled line at the given width and keeps its style on each piece', function () {
    $theme = new Theme(decorated: true);

    expect($theme->wrap($theme->muted('un deux trois quatre'), 9))->toBe(["\e[2mun deux\e[22m", "\e[2mtrois\e[22m", "\e[2mquatre\e[22m"])
        ->and($theme->wrap('abcdefghij', 4))->toBe(['abcd', 'efgh', 'ij']);
});

it('keeps each piece of a link too long for a line opening the whole address', function () {
    $theme = new Theme(decorated: true);
    $open = "\e]8;;http://a.test/abcdef\e\\";
    $close = "\e]8;;\e\\";

    expect($theme->wrap($theme->link('http://a.test/abcdef', 'http://a.test/abcdef'), 10))->toBe([
        "{$open}\e[4mhttp://a.t\e[24m{$close}",
        "{$open}\e[4mest/abcdef\e[24m{$close}",
    ]);
});
