import { usePage } from '@inertiajs/react';
import { useMemo } from 'react';
import type { Privilege } from '@/types/models/privilege';

const WRITING_PRIVILEGES: Privilege[] = ['insert', 'update', 'delete', 'create', 'alter', 'drop', 'index', 'import'];

/**
 * What the user may do, to hide what the server would refuse anyway: on the whole instance, and on the project of the page.
 */
export function usePrivileges() {
    const privileges = usePage().props.auth.privileges;

    return useMemo(() => {
        const root = privileges?.root ?? false;
        const global = privileges?.global ?? [];
        const project = privileges?.project ?? null;

        /** Whether the user holds the privilege on the whole instance. */
        const can = (privilege: Privilege): boolean => root || global.includes(privilege);

        /** Whether the user holds the privilege on the project of the page, or on the table given. */
        const canOnProject = (privilege: Privilege, table?: string): boolean =>
            root ||
            (project !== null &&
                (project.unrestricted ||
                    project.project.includes(privilege) ||
                    (table !== undefined && (project.tables[table.toLowerCase()] ?? []).includes(privilege))));

        /** Whether the user holds the privilege on the project or on at least one of its tables. */
        const canSomewhere = (privilege: Privilege): boolean =>
            canOnProject(privilege) || Object.values(project?.tables ?? {}).some((tablePrivileges) => tablePrivileges.includes(privilege));

        /** Whether the user may change the database of the project, which lets the agent write. */
        const writes = WRITING_PRIVILEGES.some(canSomewhere);

        /** Whether the user may grant privileges on the project of the page. */
        const canShare = root || (project?.share ?? false);

        return { root, can, canOnProject, canSomewhere, writes, canShare };
    }, [privileges]);
}
