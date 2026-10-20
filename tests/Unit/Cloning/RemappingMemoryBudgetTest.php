<?php

use App\Services\Cloning\RemappingMemoryBudget;

it('accepts the tables that clonio remapped within its memory limit', function (array $rows) {
    expect((new RemappingMemoryBudget)->fits($rows))->toBeTrue();
})->with([
    'nothing to remap' => [[]],
    'a table of 100 000 rows' => [['users' => 100_000]],
    'a table of 100 000 rows and small ones' => [['users' => 100_000, 'shares' => 10_000, 'events' => 5_000]],
    'two tables of 100 000 rows' => [['users' => 100_000, 'user_devices' => 100_000]],
]);

it('refuses the tables that made clonio run out of memory', function (array $rows) {
    expect((new RemappingMemoryBudget)->fits($rows))->toBeFalse();
})->with([
    'a table of 135 000 rows' => [['t' => 135_000]],
    'a table of 150 000 rows' => [['messages' => 150_000]],
    'a table of 500 000 rows' => [['comments' => 500_000]],
]);

it('keeps the tables that still fit, in the given order', function () {
    $selected = (new RemappingMemoryBudget)->select(['users' => 100_000, 'comments' => 500_000, 'devices' => 100_000, 'profiles' => 100_000, 'shares' => 10_000]);

    expect($selected)->toBe(['users', 'devices', 'shares']);
});

it('leaves room for the chunks copied while the mappings are loaded', function () {
    expect((new RemappingMemoryBudget)->fits(['shares' => 10_000]))->toBeTrue()
        ->and((new RemappingMemoryBudget(transferBytes: 80 * 1024 * 1024))->fits(['shares' => 10_000]))->toBeFalse();
});
