import type { BaseModel } from '@/types/models/base-model';

/**
 * A privilege of a user or a role, named like the cases of App\Enums\PrivilegeEnum.
 */
export type Privilege =
    | 'select'
    | 'insert'
    | 'update'
    | 'delete'
    | 'create'
    | 'alter'
    | 'drop'
    | 'index'
    | 'export'
    | 'import'
    | 'send_mail'
    | 'clone'
    | 'manage_project'
    | 'create_project'
    | 'manage_providers'
    | 'create_user';

/**
 * Where a privilege applies: the whole instance, a project or a table of its database.
 */
export type PrivilegeLevel = 'global' | 'project' | 'table';

export type GranteeType = 'user' | 'role';

export interface PrivilegeDefinition {
    value: Privilege;
    keyword: string;
    label: string;
    description: string;
    levels: PrivilegeLevel[];
}

/**
 * What the privileges editor offers: every privilege, and the projects the user may grant on.
 */
export interface PrivilegeCatalog {
    privileges: PrivilegeDefinition[];
    projects: { id: string; name: string }[];
}

/**
 * One privilege of a user or a role, on the whole instance (no project), a project (no table) or a table.
 */
export interface Grant {
    privilege: Privilege;
    project_id: string | null;
    project_name: string | null;
    table_name: string | null;
    grant_option: boolean;
}

export interface GranteePrivileges {
    grants: Grant[];
    statements: string[];
}

/**
 * One privilege granted on a project or one of its tables, with who holds it.
 */
export interface ProjectGrant {
    grantee_type: GranteeType;
    grantee_id: number;
    grantee_name: string;
    privilege: Privilege;
    table_name: string | null;
    grant_option: boolean;
}

export interface ProjectAccess {
    grants: ProjectGrant[];
    tables: string[];
    users: { id: number; name: string }[];
    roles: { id: number; name: string }[];
}

export interface Role extends BaseModel<number> {
    name: string;
    description: string | null;
    users_count?: number;
}

export interface Account extends BaseModel<number> {
    name: string;
    is_root: boolean;
    roles: Pick<Role, 'id' | 'name'>[];
}

/**
 * The privileges of the user shared with every page: those on the whole instance, and those on the project of the page.
 */
export interface SharedPrivileges {
    root: boolean;
    global: Privilege[];
    project: {
        project: Privilege[];
        tables: Record<string, Privilege[]>;
        unrestricted: boolean;
        share: boolean;
    } | null;
}
