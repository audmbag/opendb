<?php

use App\Services\TableDesigner\ColumnPositioner;
use App\Services\TableDesigner\SqliteTableDefinition;

/**
 * The order of the columns once each move put the column right after the one it names, or first.
 *
 * @param  list<string>  $columns
 * @param  list<array{column: string, after: ?string}>  $moves
 * @return list<string>
 */
function applyColumnMoves(array $columns, array $moves): array
{
    foreach ($moves as $move) {
        $columns = array_values(array_diff($columns, [$move['column']]));
        $position = $move['after'] === null ? 0 : array_search($move['after'], $columns, true) + 1;
        array_splice($columns, $position, 0, [$move['column']]);
    }

    return $columns;
}

it('moves only the columns out of place', function () {
    $positioner = new ColumnPositioner(new SqliteTableDefinition);

    expect($positioner->moves(['id', 'name', 'city', 'email'], ['id', 'email', 'name', 'city']))->toBe([['column' => 'email', 'after' => 'id']])
        ->and($positioner->moves(['id', 'name', 'city'], ['city', 'id', 'name']))->toBe([['column' => 'city', 'after' => null]])
        ->and($positioner->moves(['id', 'name'], ['id', 'name']))->toBe([]);
});

it('reaches any order of the columns', function () {
    $positioner = new ColumnPositioner(new SqliteTableDefinition);
    $columns = ['a', 'b', 'c', 'd', 'e', 'f', 'g'];

    foreach (range(1, 200) as $attempt) {
        $order = $columns;
        shuffle($order);

        expect(applyColumnMoves($columns, $positioner->moves($columns, $order)))->toBe($order);
    }
});
