<?php

use App\Services\SSH\Transports\WindowsCommandLine;

it('quotes the arguments Windows would split', function (string $argument, string $quoted) {
    expect(WindowsCommandLine::quote($argument))->toBe($quoted);
})->with([
    'plain' => ['BatchMode=yes', 'BatchMode=yes'],
    'path without spaces' => ['C:\Users\jane\.ssh\id_ed25519', 'C:\Users\jane\.ssh\id_ed25519'],
    'path with spaces' => ['C:\Users\Jane Doe\.ssh\id_ed25519', '"C:\Users\Jane Doe\.ssh\id_ed25519"'],
    'folder with spaces' => ['C:\Users\Jane Doe\\', '"C:\Users\Jane Doe\\\\"'],
    'quote' => ['say "hi"', '"say \"hi\""'],
    'backslash before a quote' => ['a\"b', '"a\\\\\"b"'],
    'empty' => ['', '""'],
]);

it('joins the arguments with spaces', function () {
    expect(WindowsCommandLine::join(['-i', 'C:\Users\Jane Doe\key', '-N']))->toBe('-i "C:\Users\Jane Doe\key" -N');
});
