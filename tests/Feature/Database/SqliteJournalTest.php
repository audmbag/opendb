<?php

use App\Database\Drivers\BaseDriver;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->path = realpath(sys_get_temp_dir()).'/'.uniqid('opendb-journal-').'.sqlite';
    File::put($this->path, '');
});

afterEach(function () {
    File::delete([$this->path, $this->path.'-wal', $this->path.'-shm']);
});

it('leaves the journal of a database of the user as it was, whatever the one of OpenDB', function () {
    config(['database.connections.sqlite.journal_mode' => 'wal', 'database.connections.sqlite.synchronous' => 'normal']);

    BaseDriver::for(['driver_id' => 'sqlite', 'database' => $this->path])->getSchema();

    expect((new PDO('sqlite:'.$this->path))->query('PRAGMA journal_mode')->fetchColumn())->toBe('delete');
});
