import { useHttp } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { LoaderIcon, PlusIcon, SaveIcon, ShieldIcon, Trash2Icon } from 'lucide-react';
import type React from 'react';
import { Fragment, useCallback, useEffect, useState } from 'react';
import { toast } from 'sonner';
import CardFrame from '@/components/card-frame';
import { EmptyState } from '@/components/empty-state';
import Button from '@/components/form/button';
import Input from '@/components/form/input';
import PrivilegeEditor from '@/components/privileges/privilege-editor';
import { Badge } from '@/components/reui/badge';
import { useSmallSideBarSubPage } from '@/components/sm-side-bar/small-side-bar';
import { Separator } from '@/components/ui/separator';
import type { APIData } from '@/types/global';
import type { Role } from '@/types/models/privilege';
import RoleController from '@/wayfinder/actions/App/Http/Controllers/Users/RoleController';

type RoleDetailsProps = {
    role: Role | null;
    onSaved: (role: Role) => void;
    onDeleted: () => void;
};

/**
 * The name and the description of a role, then its privileges, held by each of its users.
 */
function RoleDetails({ role, onSaved, onDeleted }: RoleDetailsProps) {
    const { t, tChoice } = useLaravelReactI18n();

    const form = useHttp<{ name: string; description: string }, { data: Role; message: string }>({
        name: role?.name ?? '',
        description: role?.description ?? '',
    });
    const remover = useHttp<null, { message: string }>();

    const save = (event: React.FormEvent) => {
        event.preventDefault();

        const options = {
            onSuccess: (response: { data: Role; message: string }) => {
                toast.success(response.message);
                onSaved(response.data);
            },
        };

        if (role) {
            form.put(RoleController.update.url(role.id), options);
        } else {
            form.post(RoleController.store.url(), options);
        }
    };

    const remove = () => {
        if (!role) {
            return;
        }

        remover.delete(RoleController.destroy.url(role.id), {
            onSuccess: (response) => {
                toast.success(response.message);
                onDeleted();
            },
        });
    };

    return (
        <div className="flex flex-col gap-4 pt-0.5">
            <CardFrame
                title={role ? role.name : t('users.ui.new_role')}
                icon={ShieldIcon}
                actions={
                    role && (
                        <Button
                            size="sm"
                            variant="ghost"
                            icon={Trash2Icon}
                            tooltip={t('users.ui.delete_role')}
                            processing={remover.processing}
                            onClick={remove}
                            confirmation={{
                                title: t('users.ui.delete_role_title', { name: role.name }),
                                description: tChoice('users.ui.delete_role_description', role.users_count ?? 0),
                                icon: Trash2Icon,
                            }}
                        />
                    )
                }
            >
                <form className="flex flex-col gap-4" onSubmit={save}>
                    <Input
                        label={t('users.fields.role')}
                        name="name"
                        autoComplete="off"
                        value={form.data.name}
                        onChange={(event) => form.setData('name', event.target.value)}
                        error={form.errors.name}
                    />
                    <Input
                        label={t('users.fields.description')}
                        name="description"
                        value={form.data.description}
                        onChange={(event) => form.setData('description', event.target.value)}
                        error={form.errors.description}
                    />
                    <div className="flex justify-end">
                        <Button type="submit" icon={role ? SaveIcon : PlusIcon} processing={form.processing}>
                            {role ? t('common.save') : t('users.ui.create_role')}
                        </Button>
                    </div>
                </form>
            </CardFrame>
            {role && (
                <CardFrame title={t('users.ui.privileges')} description={t('users.ui.role_privileges_description')}>
                    <PrivilegeEditor grantee={{ type: 'role', id: role.id }} />
                </CardFrame>
            )}
        </div>
    );
}

/**
 * The roles of OpenDB, like the roles of MySQL 8: named sets of privileges given to users.
 */
export default function RolesSettings() {
    const { t, tChoice } = useLaravelReactI18n();
    const [roles, setRoles] = useState<Role[]>([]);
    const [selected, setSelected] = useState<Role | 'new' | null>(null);

    useSmallSideBarSubPage(
        selected === null
            ? null
            : {
                  label: selected === 'new' ? t('users.ui.new_role') : selected.name,
                  onBack: () => setSelected(null),
              },
    );

    const loader = useHttp<null, APIData<Role>>();

    const load = useCallback(() => {
        loader.get(RoleController.index.url(), { onSuccess: (response) => setRoles(response.data) });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    useEffect(() => {
        load();
    }, [load]);

    if (selected !== null) {
        return (
            <RoleDetails
                key={selected === 'new' ? 'new' : selected.id}
                role={selected === 'new' ? null : selected}
                onSaved={(role) => {
                    load();
                    setSelected(role);
                }}
                onDeleted={() => {
                    load();
                    setSelected(null);
                }}
            />
        );
    }

    return (
        <div className="flex flex-col gap-4 pt-0.5">
            <CardFrame
                title={t('users.ui.roles_title')}
                description={t('users.ui.roles_description')}
                innerPadding={false}
                actions={
                    <Button size="sm" icon={PlusIcon} onClick={() => setSelected('new')}>
                        {t('users.ui.new_role')}
                    </Button>
                }
            >
                {loader.processing && roles.length === 0 ? (
                    <div className="flex justify-center py-4">
                        <LoaderIcon className="animate-spin" />
                    </div>
                ) : roles.length === 0 ? (
                    <EmptyState icon={ShieldIcon} title={t('users.ui.no_roles')} />
                ) : (
                    roles.map((role, index) => (
                        <Fragment key={role.id}>
                            <button
                                type="button"
                                className="flex w-full items-center justify-between gap-2 px-3 py-2 text-left transition-colors hover:bg-accent/50"
                                onClick={() => setSelected(role)}
                            >
                                <span className="flex min-w-0 flex-col">
                                    <span className="truncate text-sm">{role.name}</span>
                                    {role.description && (
                                        <span className="truncate text-xs text-muted-foreground">{role.description}</span>
                                    )}
                                </span>
                                <Badge variant="secondary">{tChoice('users.ui.users_count', role.users_count ?? 0)}</Badge>
                            </button>
                            {index !== roles.length - 1 && <Separator />}
                        </Fragment>
                    ))
                )}
            </CardFrame>
        </div>
    );
}
