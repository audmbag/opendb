<?php

namespace App\Services\Privileges\Data;

use App\Enums\PrivilegeEnum;
use App\Enums\PrivilegeLevelEnum;

/**
 * What a user may do with the tables of one project: the privileges they hold on the whole project (theirs on the whole
 * instance included) and those they hold on single tables, whose names are compared whatever their case.
 */
final readonly class TablePrivileges
{
    /**
     * @param  list<PrivilegeEnum>  $projectPrivileges
     * @param  array<string, list<PrivilegeEnum>>  $tablePrivileges  The privileges by table, named in lower case.
     */
    public function __construct(
        public array $projectPrivileges,
        public array $tablePrivileges = [],
        public bool $unrestricted = false,
    ) {}

    /**
     * Every privilege on every table, for the root.
     */
    public static function unrestricted(): self
    {
        return new self(PrivilegeEnum::forLevel(PrivilegeLevelEnum::Project), unrestricted: true);
    }

    public function allows(PrivilegeEnum $privilege, ?string $table = null): bool
    {
        return $this->allowsOnProject($privilege)
            || ($table !== null && in_array($privilege, $this->tablePrivileges[mb_strtolower($table)] ?? [], true));
    }

    public function allowsOnProject(PrivilegeEnum $privilege): bool
    {
        return $this->unrestricted || in_array($privilege, $this->projectPrivileges, true);
    }

    /**
     * Whether a privilege on the whole project reaches every table, so that none of them is out of sight.
     */
    public function reachesEveryTable(): bool
    {
        return $this->unrestricted || array_any(
            $this->projectPrivileges,
            fn (PrivilegeEnum $privilege): bool => $privilege->allowsLevel(PrivilegeLevelEnum::Table),
        );
    }

    /**
     * Whether the user may change the database, on the whole project or on one of its tables.
     */
    public function writes(): bool
    {
        return $this->unrestricted
            || array_any($this->projectPrivileges, fn (PrivilegeEnum $privilege): bool => $privilege->writes())
            || array_any($this->tablePrivileges, fn (array $privileges): bool => array_any($privileges, fn (PrivilegeEnum $privilege): bool => $privilege->writes()));
    }

    /**
     * The tables the user holds no privilege on, which stay out of their sight like the tables hidden from the agent.
     *
     * @param  list<string>  $tables
     * @return list<string>
     */
    public function hiddenAmong(array $tables): array
    {
        if ($this->reachesEveryTable()) {
            return [];
        }

        return array_values(array_filter($tables, fn (string $table): bool => ($this->tablePrivileges[mb_strtolower($table)] ?? []) === []));
    }

    /**
     * @return array{project: list<string>, tables: array<string, list<string>>, unrestricted: bool}
     */
    public function toArray(): array
    {
        $values = fn (array $privileges): array => array_map(fn (PrivilegeEnum $privilege): string => $privilege->value, $privileges);

        return [
            'project' => $values($this->projectPrivileges),
            'tables' => array_map($values, $this->tablePrivileges),
            'unrestricted' => $this->unrestricted,
        ];
    }
}
