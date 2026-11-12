<?php

namespace App\Enums;

/**
 * Where a privilege applies, like the `*.*`, `db.*` and `db.table` of MySQL: the whole instance, a project or a table of its database.
 */
enum PrivilegeLevelEnum: string
{
    case Global = 'global';

    case Project = 'project';

    case Table = 'table';

    public function label(): string
    {
        return __("privileges.levels.{$this->value}");
    }
}
