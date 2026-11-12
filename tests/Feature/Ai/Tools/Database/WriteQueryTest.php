<?php

use App\Ai\Tools\Database\WriteQuery;
use App\Models\User;
use App\Support\TranslatableMessage;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use Laravel\Ai\Tools\Request;

beforeEach(function () {
    $this->databasePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid('write-query-').'.sqlite';
    $project = projectWithSqliteDatabase(User::factory()->create(), $this->databasePath, function (Builder $schema) {
        $schema->create('contacts', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
        });
    });
    $this->driver = $project->driver_config->getDriver();
    $this->tool = new WriteQuery($this->driver);
});

afterEach(function () {
    @unlink($this->databasePath);
});

it('rejects an administration operation without running it', function () {
    $attachedPath = $this->databasePath.'.attached';

    $result = (string) $this->tool->handle(new Request([
        'query' => "ATTACH DATABASE '{$attachedPath}' AS attached",
        'bindings' => [],
    ]));

    expect($result)->toContain('réservée à un administrateur')
        ->and(file_exists($attachedPath))->toBeFalse();
});

it('does not ask to approve an administration operation that will be rejected', function () {
    expect($this->tool->shouldRequestApproval(new Request(['query' => 'DROP USER bob'])))->toBeNull();
});

it('explains what the statement does when asking for approval', function (string $sql, string $explanation) {
    $reason = $this->tool->shouldRequestApproval(new Request(['query' => $sql]))->reason;

    expect(TranslatableMessage::text(json_decode($reason, true)))->toContain($explanation);
})->with([
    'delete' => ['DELETE FROM contacts WHERE id = 1', 'supprime définitivement des lignes'],
    'drop' => ['DROP TABLE contacts', 'supprime définitivement un objet'],
    'truncate' => ['TRUNCATE TABLE contacts', 'vide entièrement la table'],
    'update' => ['  update contacts SET id = 2', 'remplace des valeurs existantes'],
    'alter dropping a column' => ['ALTER TABLE contacts DROP COLUMN role', 'modifie la structure'],
    'alter with a cascading key' => ['ALTER TABLE contacts ADD FOREIGN KEY (id) REFERENCES users (id) ON DELETE CASCADE', 'modifie la structure'],
    'trailing semicolon' => ['DELETE FROM contacts WHERE id = 1;', 'supprime définitivement des lignes'],
]);

it('does not claim what the query does when its statement is not the destructive one', function (string $sql, string $keyword) {
    $reason = TranslatableMessage::text(json_decode($this->tool->shouldRequestApproval(new Request(['query' => $sql]))->reason, true));

    expect($reason)->toContain("le mot-clé {$keyword}")
        ->not->toContain('supprime définitivement')
        ->not->toContain('remplace des valeurs');
})->with([
    'cascading key on a new table' => ['CREATE TABLE notes (id INT REFERENCES contacts (id) ON DELETE CASCADE)', 'DELETE'],
    'upsert' => ['INSERT INTO contacts (id) VALUES (1) ON CONFLICT (id) DO UPDATE SET id = 2', 'UPDATE'],
    'leading comment' => ['/* nettoyage */ DELETE FROM contacts', 'DELETE'],
    'comments looking like a text value' => ["/* ' */ DELETE FROM contacts /* ' */", 'DELETE'],
    'keyword after a dollar-quoted quote' => ["INSERT INTO contacts (name) VALUES (\$\$ ' \$\$) ON CONFLICT (id) DO UPDATE SET name = 'x'", 'UPDATE'],
]);

it('runs without approval a query whose destructive word and semicolons are only text values', function () {
    $name = "DELETE; DROP TABLE contacts; l''ami";
    $request = new Request(['query' => "INSERT INTO contacts (name) VALUES ('{$name}');", 'bindings' => []]);

    expect($this->tool->shouldRequestApproval($request))->toBeNull()
        ->and((string) $this->tool->handle($request))->toBe('Query run successfully.')
        ->and($this->driver->executeReadOnly('SELECT name FROM contacts')[0]->name)->toBe("DELETE; DROP TABLE contacts; l'ami");
});

it('rejects several statements without asking approval nor running them', function (string $sql) {
    $request = new Request(['query' => $sql, 'bindings' => []]);

    expect($this->tool->shouldRequestApproval($request))->toBeNull()
        ->and((string) $this->tool->handle($request))->toContain('les requêtes multiples')
        ->and($this->driver->executeReadOnly('SELECT COUNT(*) AS total FROM contacts')[0]->total)->toBe(0);
})->with([
    'one semicolon' => ['UPDATE contacts SET id = 2; DROP TABLE contacts'],
    'after a text value' => ["INSERT INTO contacts (name) VALUES ('a'); DROP TABLE contacts"],
    'behind a backslash that does not escape' => ["INSERT INTO contacts (name) VALUES ('a\\'); DROP TABLE contacts; --')"],
    'behind a dollar-quoted quote' => ["INSERT INTO contacts (name) VALUES (\$\$ ' \$\$); DROP TABLE contacts; --')"],
    'behind a nested comment' => ["/* /* */ ' */ DROP TABLE contacts; SELECT '"],
    'behind a bracketed quote' => ["INSERT INTO [a'] VALUES (1); DROP TABLE contacts; INSERT INTO [b'] VALUES (2)"],
    'after a hash operator' => ['UPDATE contacts SET id = 1 # 2; DROP TABLE contacts'],
    'after a double minus' => ['UPDATE contacts SET id = 5--1; DROP TABLE contacts'],
]);

it('still asks to approve then runs a change of structure', function () {
    $sql = 'ALTER TABLE contacts ADD COLUMN role VARCHAR(20)';

    expect($this->tool->shouldRequestApproval(new Request(['query' => $sql])))->not->toBeNull()
        ->and((string) $this->tool->handle(new Request(['query' => $sql, 'bindings' => []])))->toBe('Query run successfully.')
        ->and(collect($this->driver->executeReadOnly('PRAGMA table_info(contacts)'))->pluck('name'))->toContain('role');
});
