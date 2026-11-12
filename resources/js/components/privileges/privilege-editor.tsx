import { useLaravelReactI18n } from 'laravel-react-i18n';
import { DatabaseIcon, GlobeIcon, LoaderIcon, PlusIcon, Table2Icon } from 'lucide-react';
import { memo, useCallback, useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';
import { CodeBlock } from '@/components/ai-elements/code-block';
import CardFrame from '@/components/card-frame';
import Button from '@/components/form/button';
import Select from '@/components/form/select';
import type { TableRequestError } from '@/components/tables/api';
import { requestTableData } from '@/components/tables/api';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import type {
    Grant,
    GranteePrivileges,
    GranteeType,
    Privilege,
    PrivilegeCatalog,
    PrivilegeDefinition,
    PrivilegeLevel,
    ProjectAccess,
} from '@/types/models/privilege';
import GrantController from '@/wayfinder/actions/App/Http/Controllers/Users/GrantController';

/**
 * The privileges, gathered as the interface shows them.
 */
const PRIVILEGE_GROUPS: { key: string; privileges: Privilege[] }[] = [
    { key: 'data', privileges: ['select', 'insert', 'update', 'delete'] },
    { key: 'structure', privileges: ['create', 'alter', 'drop', 'index'] },
    { key: 'features', privileges: ['export', 'import', 'send_mail', 'clone', 'manage_project'] },
    { key: 'administration', privileges: ['create_project', 'manage_providers', 'create_user'] },
];

/**
 * Where privileges are granted: the whole instance, a project, or one of its tables.
 */
type Scope = {
    projectId: string | null;
    projectName: string | null;
    table: string | null;
};

const scopeKey = (scope: Pick<Scope, 'projectId' | 'table'>): string =>
    `${scope.projectId ?? '*'}:${scope.table?.toLowerCase() ?? '*'}`;

/**
 * What a tick does: grant or revoke privileges, or only give or take back the right to grant them.
 */
type ToggleAction = 'grant' | 'revoke' | 'grant-option' | 'revoke-grant-option';

/**
 * The value of the table list standing for the whole project, since a value of a list cannot be empty.
 */
const WHOLE_PROJECT = '*';

const levelOf = (scope: Scope): PrivilegeLevel =>
    scope.projectId === null ? 'global' : scope.table === null ? 'project' : 'table';

type ScopeCardProps = {
    scope: Scope;
    grants: Grant[];
    definitions: Map<Privilege, PrivilegeDefinition>;
    pending: string | null;
    onToggle: (scope: Scope, privileges: Privilege[], action: ToggleAction, grantOption: boolean) => void;
};

/**
 * The privileges of one level, ticked when held: a tick grants, an untick revokes.
 */
const ScopeCard = memo(function ScopeCard({ scope, grants, definitions, pending, onToggle }: ScopeCardProps) {
    const { t } = useLaravelReactI18n();
    const level = levelOf(scope);
    const held = new Map(grants.map((grant) => [grant.privilege, grant]));
    const grantOption = grants.length > 0 && grants.every((grant) => grant.grant_option);
    const key = scopeKey(scope);
    const busy = pending?.startsWith(key) ?? false;

    const title =
        level === 'global'
            ? t('privileges.levels.global')
            : level === 'project'
              ? `${scope.projectName} .*`
              : `${scope.projectName} . ${scope.table}`;

    return (
        <CardFrame
            title={title}
            icon={level === 'global' ? GlobeIcon : level === 'project' ? DatabaseIcon : Table2Icon}
            processing={busy}
            actions={
                <Label className="flex items-center gap-2 text-xs font-normal text-muted-foreground">
                    <Checkbox
                        checked={grantOption}
                        disabled={grants.length === 0 || busy}
                        onCheckedChange={(checked) =>
                            onToggle(
                                scope,
                                grants.map((grant) => grant.privilege),
                                checked === true ? 'grant-option' : 'revoke-grant-option',
                                checked === true,
                            )
                        }
                    />
                    GRANT OPTION
                </Label>
            }
        >
            <div className="flex flex-col gap-3">
                {PRIVILEGE_GROUPS.map((group) => {
                    const privileges = group.privileges.filter((privilege) =>
                        definitions.get(privilege)?.levels.includes(level),
                    );

                    if (privileges.length === 0) {
                        return null;
                    }

                    return (
                        <div key={group.key} className="flex flex-col gap-1.5">
                            <span className="text-xs text-muted-foreground">
                                {t(`privileges.groups.${group.key}`)}
                            </span>
                            <div className="grid grid-cols-1 gap-x-4 gap-y-1.5 sm:grid-cols-2">
                                {privileges.map((privilege) => {
                                    const definition = definitions.get(privilege)!;

                                    return (
                                        <Label
                                            key={privilege}
                                            title={definition.description}
                                            className={cn(
                                                'flex items-center gap-2 font-normal',
                                                busy && 'opacity-60',
                                            )}
                                        >
                                            <Checkbox
                                                checked={held.has(privilege)}
                                                disabled={busy}
                                                onCheckedChange={(checked) =>
                                                    onToggle(scope, [privilege], checked === true ? 'grant' : 'revoke', grantOption)
                                                }
                                            />
                                            <span className="font-mono text-xs">{definition.keyword}</span>
                                            <span className="truncate text-xs text-muted-foreground">
                                                {definition.label}
                                            </span>
                                        </Label>
                                    );
                                })}
                            </div>
                        </div>
                    );
                })}
            </div>
        </CardFrame>
    );
});

type Props = {
    grantee: { type: GranteeType; id: number };
    /**
     * Only the levels of this project, and of its tables, are shown.
     */
    project?: { id: string; name: string };
    /**
     * Called once the privileges changed, with what the grantee now holds.
     */
    onChange?: (privileges: GranteePrivileges) => void;
};

/**
 * The privileges of a user or a role, by level, granted and revoked as they are ticked, like GRANT and REVOKE do.
 */
export default function PrivilegeEditor({ grantee, project, onChange }: Props) {
    const { t } = useLaravelReactI18n();
    const [catalog, setCatalog] = useState<PrivilegeCatalog | null>(null);
    const [privileges, setPrivileges] = useState<GranteePrivileges | null>(null);
    const [addedScopes, setAddedScopes] = useState<Scope[]>([]);
    const [pending, setPending] = useState<string | null>(null);
    const [newProjectId, setNewProjectId] = useState<string>(project?.id ?? '');
    const [newTable, setNewTable] = useState<string>(WHOLE_PROJECT);
    const [tables, setTables] = useState<Record<string, string[]>>({});

    useEffect(() => {
        const controller = new AbortController();

        Promise.all([
            requestTableData<PrivilegeCatalog>('get', GrantController.catalog.url(), { signal: controller.signal }),
            requestTableData<GranteePrivileges>(
                'get',
                GrantController.index.url({
                    query: { grantee_type: grantee.type, grantee_id: grantee.id, ...(project ? { project_id: project.id } : {}) },
                }),
                { signal: controller.signal },
            ),
        ])
            .then(([loadedCatalog, loadedPrivileges]) => {
                setCatalog(loadedCatalog);
                setPrivileges(loadedPrivileges);
            })
            .catch((error: TableRequestError) => !error.cancelled && toast.error(error.message));

        return () => controller.abort();
        // The project is given as a new object at each render: its id alone tells whether it changed.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [grantee.type, grantee.id, project?.id]);

    const loadTables = useCallback(
        (projectId: string) => {
            if (projectId === '' || tables[projectId]) {
                return;
            }

            requestTableData<ProjectAccess>('get', GrantController.project.url(projectId))
                .then((access) => setTables((current) => ({ ...current, [projectId]: access.tables })))
                .catch((error: TableRequestError) => toast.error(error.message));
        },
        [tables],
    );

    useEffect(() => {
        loadTables(newProjectId);
    }, [newProjectId, loadTables]);

    const definitions = useMemo(
        () => new Map((catalog?.privileges ?? []).map((definition) => [definition.value, definition])),
        [catalog],
    );

    const projectNames = useMemo(
        () => new Map((catalog?.projects ?? []).map((item) => [item.id, item.name])),
        [catalog],
    );

    /**
     * The levels holding a privilege, then those added to grant on, the whole instance first.
     */
    const scopes = useMemo(() => {
        const byKey = new Map<string, Scope>();

        if (!project) {
            byKey.set(scopeKey({ projectId: null, table: null }), { projectId: null, projectName: null, table: null });
        } else {
            byKey.set(scopeKey({ projectId: project.id, table: null }), { projectId: project.id, projectName: project.name, table: null });
        }

        for (const grant of privileges?.grants ?? []) {
            if (project && grant.project_id !== project.id) {
                continue;
            }

            const scope = {
                projectId: grant.project_id,
                projectName: grant.project_name,
                table: grant.table_name,
            };

            byKey.set(scopeKey(scope), byKey.get(scopeKey(scope)) ?? scope);
        }

        for (const scope of addedScopes) {
            byKey.set(scopeKey(scope), byKey.get(scopeKey(scope)) ?? scope);
        }

        const rank = (scope: Scope) => (scope.projectId === null ? 0 : scope.table === null ? 1 : 2);

        return [...byKey.values()].sort(
            (left, right) =>
                (left.projectName ?? '').localeCompare(right.projectName ?? '') ||
                rank(left) - rank(right) ||
                (left.table ?? '').localeCompare(right.table ?? ''),
        );
    }, [privileges, addedScopes, project]);

    const grantsOf = useCallback(
        (scope: Scope) =>
            (privileges?.grants ?? []).filter((grant) => scopeKey({ projectId: grant.project_id, table: grant.table_name }) === scopeKey(scope)),
        [privileges],
    );

    const toggle = useCallback(
        (scope: Scope, toggled: Privilege[], action: ToggleAction, grantOption: boolean) => {
            if (toggled.length === 0) {
                return;
            }

            const grants = action === 'grant' || action === 'grant-option';

            setPending(`${scopeKey(scope)}:${toggled.join(',')}`);

            requestTableData<GranteePrivileges>(grants ? 'post' : 'delete', grants ? GrantController.store.url() : GrantController.destroy.url(), {
                data: {
                    grantee_type: grantee.type,
                    grantee_id: grantee.id,
                    privileges: toggled,
                    project_id: scope.projectId,
                    table: scope.table,
                    // On a revocation, the grant option alone is taken back.
                    grant_option: action === 'revoke-grant-option' || (action !== 'revoke' && grantOption),
                },
            })
                .then((changed) => {
                    setPrivileges(changed);
                    onChange?.(changed);
                })
                .catch((error: TableRequestError) => toast.error(error.first()))
                .finally(() => setPending(null));
        },
        [grantee.type, grantee.id, onChange],
    );

    const addScope = () => {
        const projectId = project?.id ?? newProjectId;

        if (projectId === '') {
            return;
        }

        setAddedScopes((current) => [
            ...current,
            {
                projectId,
                projectName: project?.name ?? projectNames.get(projectId) ?? projectId,
                table: newTable === WHOLE_PROJECT ? null : newTable,
            },
        ]);
        setNewTable(WHOLE_PROJECT);
    };

    if (catalog === null || privileges === null) {
        return (
            <div className="flex justify-center py-6">
                <LoaderIcon className="animate-spin text-muted-foreground" />
            </div>
        );
    }

    const projectItems = catalog.projects.map((item) => ({ label: item.name, value: item.id }));
    const tableItems = [
        { label: t('privileges.editor.whole_project'), value: WHOLE_PROJECT },
        ...(tables[project?.id ?? newProjectId] ?? []).map((table) => ({ label: table, value: table })),
    ];

    return (
        <div className="flex flex-col gap-4">
            {scopes.map((scope) => (
                <ScopeCard
                    key={scopeKey(scope)}
                    scope={scope}
                    grants={grantsOf(scope)}
                    definitions={definitions}
                    pending={pending}
                    onToggle={toggle}
                />
            ))}

            {(project || projectItems.length > 0) && (
                <div className="flex flex-col items-stretch gap-2 sm:flex-row sm:items-end">
                    {!project && (
                        <Select
                            label={t('privileges.editor.project')}
                            name="project"
                            value={newProjectId}
                            onValueChange={(value) => {
                                setNewProjectId(value);
                                setNewTable(WHOLE_PROJECT);
                            }}
                            items={projectItems}
                        />
                    )}
                    <Select
                        label={t('privileges.editor.table')}
                        name="table"
                        value={newTable}
                        onValueChange={setNewTable}
                        items={tableItems}
                        disabled={(project?.id ?? newProjectId) === ''}
                    />
                    <Button
                        variant="secondary"
                        icon={PlusIcon}
                        onClick={addScope}
                        disabled={(project?.id ?? newProjectId) === '' || (project !== undefined && newTable === WHOLE_PROJECT)}
                    >
                        {t('privileges.editor.add_level')}
                    </Button>
                </div>
            )}

            <div className="flex flex-col gap-1.5">
                <span className="text-xs text-muted-foreground">SHOW GRANTS</span>
                <CodeBlock code={privileges.statements.join(';\n') + ';'} language="sql" />
            </div>
        </div>
    );
}
