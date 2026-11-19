<?php

use App\Console\Commands\Chat\Output\Terminal;
use App\Console\Commands\Chat\Turn\AnswerInterrupted;
use App\Console\Commands\Chat\Turn\CancelHandler;
use Symfony\Component\Console\Output\BufferedOutput;

it('writes the answer in the chat process where it cannot be forked', function () {
    $written = false;

    $answered = new CancelHandler(new Terminal(new BufferedOutput))->watch(function () use (&$written): void {
        $written = true;
    });

    expect($answered)->toBeTrue()
        ->and($written)->toBeTrue();
});

it('gives the chat back when Ctrl+C interrupts the answer', function () {
    $terminal = new Terminal($output = new BufferedOutput);
    $terminal->openFrame('run_sql');

    $answered = new CancelHandler($terminal)->watch(fn () => throw new AnswerInterrupted);

    expect($answered)->toBeFalse()
        ->and($output->fetch())->toContain('╰');
});
