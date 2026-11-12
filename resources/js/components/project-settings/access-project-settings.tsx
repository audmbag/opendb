import { usePage } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { KeyRoundIcon, LoaderIcon, ShieldIcon, UserIcon } from 'lucide-react';
import { Fragment, useCallback, useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';
import CardFrame from '@/components/card-frame';
import { EmptyState } from '@/components/empty-state';
import Select from '@/components/form/select';
import PrivilegeEditor from '@/components/privileges/privilege-editor';
import { Badge } from '@/components/reui/badge';
import { useSmallSideBarSubPage } from '@/components/sm-side-bar/small-side-bar';
import type { TableRequestError } from '@/components/tables/api';
import { requestTableData } from '@/components/tables/api';
import { Separator } from '@/components/ui/separator';
import type { GranteeType, ProjectAccess } from '@/types/models/privilege';
import type { Project } from '@/types/models/project';
import GrantController from '@/wayfinder/actions/App/Http/Controllers/Users/GrantController';

type Grantee = { type: GranteeType; id: number; name: string };

const granteeKey = (grantee: Pick<Grantee, 'type' | 'id'>) => `${grantee.type}:${grantee.id}`;

/**
 * Who may open the project and what they may do with it and its tables, granted by who holds the grant option on it.
 */
export default function AccessProjectSettings() {
    const { t, tChoice } = useLaravelReactI18n();
    const { project } = usePage<{ project: Project }>().props;
    const [access, setAccess] = useState<ProjectAccess | null>(null);
    const [selected, setSelected] = useState<Grantee | null>(null);
    const [newGrantee, setNewGrantee] = useState('');

    const load = useCallback(() => {
        requestTableData<ProjectAccess>('get', GrantController.project.url(project.id))
            .then(setAccess)
            .catch((error: TableRequestError) => toast.error(error.message));
    }, [project.id]);

    useEffect(() => {
        load();
    }, [load]);

    /**
     * Each grantee with the privileges they hold on the project and the number of tables they hold some on.
     */
    const grantees = useMemo(() => {
        const byKey = new Map<string, Grantee & { onProject: string[]; tables: Set<string> }>();

        for (const grant of access?.grants ?? []) {
            const key = granteeKey({ type: grant.grantee_type, id: grant.grantee_id });
            const entry = byKey.get(key) ?? {
                type: grant.grantee_type,
                id: grant.grantee_id,
                name: grant.grantee_name,
                onProject: [],
                tables: new Set<string>(),
            };

            if (grant.table_name === null) {
                entry.onProject.push(grant.privilege);
            } else {
                entry.tables.add(grant.table_name);
            }

            byKey.set(key, entry);
        }

        return [...byKey.values()].sort((left, right) => left.name.localeCompare(right.name));
    }, [access]);

    useSmallSideBarSubPage(
        selected === null
            ? null
            : {
                  label: selected.name,
                  onBack: () => {
                      setSelected(null);
                      load();
                  },
              },
    );

    if (selected !== null) {
        return (
            <div className="flex flex-col gap-4 pt-0.5">
                <CardFrame
                    title={selected.name}
                    description={t('users.ui.access_editor_description', { project: project.name })}
                    icon={selected.type === 'role' ? ShieldIcon : UserIcon}
                >
                    <PrivilegeEditor grantee={selected} project={{ id: project.id, name: project.name }} />
                </CardFrame>
            </div>
        );
    }

    const candidates = [
        ...(access?.users ?? []).map((user) => ({ label: user.name, value: `user:${user.id}`, icon: UserIcon })),
        ...(access?.roles ?? []).map((role) => ({ label: role.name, value: `role:${role.id}`, icon: ShieldIcon })),
    ];

    const open = (value: string) => {
        const [type, id] = value.split(':');
        const candidate = candidates.find((item) => item.value === value);

        if (candidate) {
            setSelected({ type: type as GranteeType, id: Number(id), name: candidate.label });
        }
    };

    return (
        <div className="flex flex-col gap-4 pt-0.5">
            <CardFrame
                title={t('users.ui.access_title')}
                description={t('users.ui.access_description')}
                icon={KeyRoundIcon}
                innerPadding={false}
            >
                {access === null ? (
                    <div className="flex justify-center py-4">
                        <LoaderIcon className="animate-spin" />
                    </div>
                ) : grantees.length === 0 ? (
                    <EmptyState icon={KeyRoundIcon} title={t('users.ui.no_access')} />
                ) : (
                    grantees.map((grantee, index) => (
                        <Fragment key={granteeKey(grantee)}>
                            <button
                                type="button"
                                className="flex w-full items-center justify-between gap-2 px-3 py-2 text-left transition-colors hover:bg-accent/50"
                                onClick={() => setSelected(grantee)}
                            >
                                <span className="flex min-w-0 items-center gap-2">
                                    {grantee.type === 'role' ? (
                                        <ShieldIcon className="size-4 shrink-0 text-muted-foreground" />
                                    ) : (
                                        <UserIcon className="size-4 shrink-0 text-muted-foreground" />
                                    )}
                                    <span className="truncate text-sm">{grantee.name}</span>
                                </span>
                                <span className="flex flex-wrap justify-end gap-1">
                                    {grantee.onProject.length > 0 && (
                                        <Badge variant="secondary">
                                            {tChoice('users.ui.project_privileges_count', grantee.onProject.length)}
                                        </Badge>
                                    )}
                                    {grantee.tables.size > 0 && (
                                        <Badge variant="outline">
                                            {tChoice('users.ui.tables_count', grantee.tables.size)}
                                        </Badge>
                                    )}
                                </span>
                            </button>
                            {index !== grantees.length - 1 && <Separator />}
                        </Fragment>
                    ))
                )}
            </CardFrame>
            {candidates.length > 0 && (
                <CardFrame title={t('users.ui.give_access')} description={t('users.ui.give_access_description')}>
                    <Select
                        label={t('users.ui.choose_grantee')}
                        name="grantee"
                        value={newGrantee}
                        onValueChange={(value) => {
                            setNewGrantee(value);
                            open(value);
                        }}
                        items={candidates}
                    />
                </CardFrame>
            )}
        </div>
    );
}
