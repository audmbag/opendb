<?php

declare(strict_types=1);

namespace App\Services\Importer;

use App\Database\Contracts\DriverContract;
use App\Database\Exceptions\TableNotFoundException;
use RuntimeException;

/**
 * Passerelle entre l'application et l'import de données : un script SQL dans la base, un fichier CSV dans une table.
 */
final readonly class ImporterManager
{
    public function __construct(
        private ImportFileReader $files,
        private SqlStatementSplitter $splitter,
        private SqlScriptRunner $runner,
        private CsvImporter $csv,
    ) {}

    /**
     * @return array{statements: int, executed: int, skipped: int, failed: int, failures: list<array{index: int, statement: string, message: string}>, kept: bool, duration: int}
     *
     * @throws RuntimeException when the script holds no statement
     */
    public function importSql(DriverContract $driver, string $sql, ImportMode $mode): array
    {
        $statements = $this->splitter->split($sql, $driver->getConnection()->getDriverName());

        if ($statements === []) {
            throw new RuntimeException('Le script ne contient aucune instruction SQL.');
        }

        return $this->runner->run($driver, $statements, $mode);
    }

    /**
     * @return array{statements: int, executed: int, skipped: int, failed: int, failures: list<array{index: int, statement: string, message: string}>, kept: bool, duration: int}
     *
     * @throws RuntimeException when the file cannot be read or holds no statement
     */
    public function importSqlFile(DriverContract $driver, string $path, string $name, ImportMode $mode): array
    {
        return $this->importSql($driver, $this->files->read($path, $name, 'sql'), $mode);
    }

    /**
     * @param  list<?string>  $mapping  the table column of each column of the file, null to leave it out
     * @param  array{delimiter: string, header: bool, emptyAsNull: bool, replace: bool}  $options
     * @return array{inserted: int, deleted: int, duration: int}
     *
     * @throws RuntimeException|TableNotFoundException
     */
    public function importCsv(DriverContract $driver, string $table, string $path, string $name, array $mapping, array $options): array
    {
        return $this->csv->import($driver, $table, $this->files->read($path, $name, 'csv'), $mapping, $options);
    }

    /**
     * The statements of a script, as they would run one after the other.
     *
     * @return list<string>
     */
    public function statements(DriverContract $driver, string $sql): array
    {
        return $this->splitter->split($sql, $driver->getConnection()->getDriverName());
    }
}
