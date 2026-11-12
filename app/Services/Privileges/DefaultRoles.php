<?php

namespace App\Services\Privileges;

use App\Enums\PrivilegeEnum;
use App\Enums\PrivilegeLevelEnum;
use App\Models\Role;

/**
 * The roles OpenDB is installed with, on the whole instance, that an administrator may change or delete afterwards.
 */
class DefaultRoles
{
    public function __construct(private GrantWriter $writer) {}

    /**
     * Create the roles that do not exist yet, with their privileges.
     */
    public function install(): void
    {
        foreach ($this->definitions() as $key => ['privileges' => $privileges, 'grant_option' => $grantOption]) {
            $role = Role::query()->firstOrCreate(
                ['name' => __("privileges.roles.{$key}.name")],
                ['description' => __("privileges.roles.{$key}.description")],
            );

            if ($role->wasRecentlyCreated) {
                $this->writer->grant(null, $role, $privileges, grantOption: $grantOption);
            }
        }
    }

    /**
     * @return array<string, array{privileges: list<PrivilegeEnum>, grant_option: bool}>
     */
    private function definitions(): array
    {
        return [
            'admin' => [
                'privileges' => PrivilegeEnum::forLevel(PrivilegeLevelEnum::Global),
                'grant_option' => true,
            ],
            'developer' => [
                'privileges' => [
                    PrivilegeEnum::Select, PrivilegeEnum::Insert, PrivilegeEnum::Update, PrivilegeEnum::Delete,
                    PrivilegeEnum::Create, PrivilegeEnum::Alter, PrivilegeEnum::Drop, PrivilegeEnum::Index,
                    PrivilegeEnum::Export, PrivilegeEnum::Import, PrivilegeEnum::CreateProject,
                ],
                'grant_option' => false,
            ],
            'analyst' => [
                'privileges' => [PrivilegeEnum::Select, PrivilegeEnum::Export],
                'grant_option' => false,
            ],
        ];
    }
}
