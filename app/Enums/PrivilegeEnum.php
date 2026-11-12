<?php

namespace App\Enums;

use ValueError;

/**
 * The privileges a user or a role may be granted, after those of MySQL, with the ones OpenDB adds for its own features.
 */
enum PrivilegeEnum: string
{
    case Select = 'select';

    case Insert = 'insert';

    case Update = 'update';

    case Delete = 'delete';

    case Create = 'create';

    case Alter = 'alter';

    case Drop = 'drop';

    case Index = 'index';

    case Export = 'export';

    case Import = 'import';

    case SendMail = 'send_mail';

    case Clone = 'clone';

    case ManageProject = 'manage_project';

    case CreateProject = 'create_project';

    case ManageProviders = 'manage_providers';

    case CreateUser = 'create_user';

    /**
     * The keyword of the privilege in a GRANT statement, like `SEND MAIL`.
     */
    public function keyword(): string
    {
        return strtoupper(str_replace('_', ' ', $this->value));
    }

    public function label(): string
    {
        return __("privileges.names.{$this->value}");
    }

    public function description(): string
    {
        return __("privileges.descriptions.{$this->value}");
    }

    /**
     * The levels the privilege may be granted at: a privilege about the rows or the structure of a table may be given for one table,
     * one about a whole project for a project, and the administration of OpenDB only for the whole instance.
     *
     * @return list<PrivilegeLevelEnum>
     */
    public function levels(): array
    {
        return match ($this) {
            self::Select, self::Insert, self::Update, self::Delete, self::Alter, self::Drop, self::Index, self::Export, self::Import => PrivilegeLevelEnum::cases(),
            self::Create, self::SendMail, self::Clone, self::ManageProject => [PrivilegeLevelEnum::Global, PrivilegeLevelEnum::Project],
            self::CreateProject, self::ManageProviders, self::CreateUser => [PrivilegeLevelEnum::Global],
        };
    }

    public function allowsLevel(PrivilegeLevelEnum $level): bool
    {
        return in_array($level, $this->levels(), true);
    }

    /**
     * Whether the privilege lets its holder change the database of a project.
     */
    public function writes(): bool
    {
        return in_array($this, self::writing(), true);
    }

    /**
     * The privileges changing the rows or the structure of a database.
     *
     * @return list<self>
     */
    public static function writing(): array
    {
        return [self::Insert, self::Update, self::Delete, self::Create, self::Alter, self::Drop, self::Index, self::Import];
    }

    /**
     * Every privilege that may be granted at the level, which `ALL PRIVILEGES` stands for.
     *
     * @return list<self>
     */
    public static function forLevel(PrivilegeLevelEnum $level): array
    {
        return array_values(array_filter(self::cases(), fn (self $privilege): bool => $privilege->allowsLevel($level)));
    }

    /**
     * The privilege written like in a GRANT statement or on a command line: `SELECT`, `send mail`, `send-mail` or `send_mail`.
     *
     * @throws ValueError when no privilege has this name.
     */
    public static function fromKeyword(string $keyword): self
    {
        return self::from(strtolower((string) preg_replace('/[\s-]+/', '_', trim($keyword))));
    }
}
