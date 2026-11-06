<?php

use App\Services\Importer\SqlStatementSplitter;

it('splits on the delimiters outside text values, identifiers and comments', function () {
    $sql = "-- header; comment\nINSERT INTO t VALUES ('a;b', \"c;d\", 'it''s');\n/* block; */ SELECT `x;y` FROM t;   \n-- trailing";

    expect(new SqlStatementSplitter()->split($sql, 'sqlite'))->toBe([
        "INSERT INTO t VALUES ('a;b', \"c;d\", 'it''s')",
        'SELECT `x;y` FROM t',
    ]);
});

it('reads backslash escapes and DELIMITER lines on MySQL', function () {
    $sql = "INSERT INTO t VALUES ('a\\';b');\nDELIMITER //\nCREATE PROCEDURE p() BEGIN SELECT 1; END//\nDELIMITER ;\nSELECT 2;";

    expect(new SqlStatementSplitter()->split($sql, 'mysql'))->toBe([
        "INSERT INTO t VALUES ('a\\';b')",
        'CREATE PROCEDURE p() BEGIN SELECT 1; END',
        'SELECT 2',
    ]);
});

it('keeps dollar-quoted bodies whole on PostgreSQL and trigger blocks whole on SQLite', function () {
    $function = "CREATE FUNCTION f() RETURNS int AS \$body\$ BEGIN RETURN 1; END; \$body\$ LANGUAGE plpgsql;\nSELECT 1;";
    $trigger = "CREATE TRIGGER t AFTER INSERT ON a BEGIN UPDATE b SET c = 1; DELETE FROM d; END;\nSELECT 1;";

    expect(new SqlStatementSplitter()->split($function, 'pgsql'))->toHaveCount(2)
        ->and(new SqlStatementSplitter()->split($trigger, 'sqlite'))->toBe([
            'CREATE TRIGGER t AFTER INSERT ON a BEGIN UPDATE b SET c = 1; DELETE FROM d; END',
            'SELECT 1',
        ]);
});
