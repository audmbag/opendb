import { useHttp, usePage } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { CrownIcon, LoaderIcon, SaveIcon, Trash2Icon, UserIcon, UserPlusIcon, UsersIcon } from 'lucide-react';
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
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import type { APIData } from '@/types/global';
import type { Account, Role } from '@/types/models/privilege';
import RoleController from '@/wayfinder/actions/App/Http/Controllers/Users/RoleController';
import UserController from '@/wayfinder/actions/App/Http/Controllers/Users/UserController';

type AccountForm = {
    name: string;
    password: string;
    password_confirmation: string;
    roles: number[];
};

type AccountFormProps = {
    account: Account | null;
    roles: Role[];
    onSaved: (account: Account) => void;
    onDeleted: (account: Account) => void;
};

/**
 * The name, the password and the roles of an account, then its own privileges.
 */
function AccountDetails({ account, roles, onSaved, onDeleted }: AccountFormProps) {
    const { t } = useLaravelReactI18n();
    const { auth } = usePage().props;

    const form = useHttp<AccountForm, { data: Account; message: string }>({
        name: account?.name ?? '',
        password: '',
        password_confirmation: '',
        roles: account?.roles.map((role) => role.id) ?? [],
    });
    const remover = useHttp<null, { message: string }>();

    const save = (event: React.FormEvent) => {
        event.preventDefault();

        const options = {
            onSuccess: (response: { data: Account; message: string }) => {
                toast.success(response.message);
                form.setData({ ...form.data, password: '', password_confirmation: '' });
                onSaved(response.data);
            },
        };

        if (account) {
            form.put(UserController.update.url(account.id), options);
        } else {
            form.post(UserController.store.url(), options);
        }
    };

    const remove = () => {
        if (!account) {
            return;
        }

        remover.delete(UserController.destroy.url(account.id), {
            onSuccess: (response) => {
                toast.success(response.message);
                onDeleted(account);
            },
        });
    };

    const toggleRole = (roleId: number, checked: boolean) =>
        form.setData(
            'roles',
            checked ? [...form.data.roles, roleId] : form.data.roles.filter((id) => id !== roleId),
        );

    const isRoot = account?.is_root ?? false;
    const isSelf = account?.id === auth.user.id;

    return (
        <div className="flex flex-col gap-4 pt-0.5">
            <CardFrame
                title={account ? account.name : t('users.ui.new_user')}
                description={isRoot ? t('users.ui.root_description') : undefined}
                icon={isRoot ? CrownIcon : UserIcon}
                actions={
                    account &&
                    !isRoot &&
                    !isSelf && (
                        <Button
                            size="sm"
                            variant="ghost"
                            icon={Trash2Icon}
                            tooltip={t('users.ui.delete_user')}
                            processing={remover.processing}
                            onClick={remove}
                            confirmation={{
                                title: t('users.ui.delete_user_title', { name: account.name }),
                                description: t('users.ui.delete_user_description'),
                                icon: Trash2Icon,
                            }}
                        />
                    )
                }
            >
                <form className="flex flex-col gap-4" onSubmit={save}>
                    <Input
                        label={t('users.fields.name')}
                        name="name"
                        autoComplete="off"
                        value={form.data.name}
                        onChange={(event) => form.setData('name', event.target.value)}
                        error={form.errors.name}
                        disabled={isRoot && !auth.privileges?.root}
                    />
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Input
                            label={t('users.fields.password')}
                            name="password"
                            type="password"
                            autoComplete="new-password"
                            placeholder={account ? t('users.ui.unchanged') : undefined}
                            value={form.data.password}
                            onChange={(event) => form.setData('password', event.target.value)}
                            error={form.errors.password}
                        />
                        <Input
                            label={t('users.fields.password_confirmation')}
                            name="password_confirmation"
                            type="password"
                            autoComplete="new-password"
                            value={form.data.password_confirmation}
                            onChange={(event) => form.setData('password_confirmation', event.target.value)}
                        />
                    </div>
                    {!isRoot && roles.length > 0 && (
                        <div className="flex flex-col gap-2">
                            <span className="text-sm font-medium">{t('users.fields.roles')}</span>
                            <div className="grid gap-2 sm:grid-cols-2">
                                {roles.map((role) => (
                                    <Label key={role.id} className="flex items-start gap-2 font-normal">
                                        <Checkbox
                                            checked={form.data.roles.includes(role.id)}
                                            onCheckedChange={(checked) => toggleRole(role.id, checked === true)}
                                        />
                                        <span className="flex flex-col gap-0.5">
                                            <span>{role.name}</span>
                                            {role.description && (
                                                <span className="text-xs text-muted-foreground">{role.description}</span>
                                            )}
                                        </span>
                                    </Label>
                                ))}
                            </div>
                        </div>
                    )}
                    <div className="flex justify-end">
                        <Button type="submit" icon={account ? SaveIcon : UserPlusIcon} processing={form.processing}>
                            {account ? t('common.save') : t('users.ui.create_user')}
                        </Button>
                    </div>
                </form>
            </CardFrame>
            {account && !isRoot && (
                <CardFrame title={t('users.ui.privileges')} description={t('users.ui.privileges_description')}>
                    <PrivilegeEditor grantee={{ type: 'user', id: account.id }} />
                </CardFrame>
            )}
        </div>
    );
}

/**
 * The users of OpenDB, like the accounts of MySQL: their roles and their own privileges.
 */
export default function UsersSettings() {
    const { t } = useLaravelReactI18n();
    const [accounts, setAccounts] = useState<Account[]>([]);
    const [roles, setRoles] = useState<Role[]>([]);
    const [selected, setSelected] = useState<Account | 'new' | null>(null);

    useSmallSideBarSubPage(
        selected === null
            ? null
            : {
                  label: selected === 'new' ? t('users.ui.new_user') : selected.name,
                  onBack: () => setSelected(null),
              },
    );

    const loader = useHttp<null, APIData<Account>>();
    const rolesLoader = useHttp<null, APIData<Role>>();

    const load = useCallback(() => {
        loader.get(UserController.index.url(), { onSuccess: (response) => setAccounts(response.data) });
        rolesLoader.get(RoleController.index.url(), { onSuccess: (response) => setRoles(response.data) });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    useEffect(() => {
        load();
    }, [load]);

    if (selected !== null) {
        return (
            <AccountDetails
                key={selected === 'new' ? 'new' : selected.id}
                account={selected === 'new' ? null : selected}
                roles={roles}
                onSaved={(account) => {
                    load();
                    setSelected(account);
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
                title={t('users.ui.title')}
                description={t('users.ui.description')}
                innerPadding={false}
                actions={
                    <Button size="sm" icon={UserPlusIcon} onClick={() => setSelected('new')}>
                        {t('users.ui.new_user')}
                    </Button>
                }
            >
                {loader.processing && accounts.length === 0 ? (
                    <div className="flex justify-center py-4">
                        <LoaderIcon className="animate-spin" />
                    </div>
                ) : accounts.length === 0 ? (
                    <EmptyState icon={UsersIcon} title={t('users.ui.no_users')} />
                ) : (
                    accounts.map((account, index) => (
                        <Fragment key={account.id}>
                            <button
                                type="button"
                                className="flex w-full items-center justify-between gap-2 px-3 py-2 text-left transition-colors hover:bg-accent/50"
                                onClick={() => setSelected(account)}
                            >
                                <span className="flex min-w-0 items-center gap-2">
                                    {account.is_root ? (
                                        <CrownIcon className="size-4 shrink-0 text-warning" />
                                    ) : (
                                        <UserIcon className="size-4 shrink-0 text-muted-foreground" />
                                    )}
                                    <span className="truncate text-sm">{account.name}</span>
                                    {account.is_root && <Badge variant="warning-light">root</Badge>}
                                </span>
                                <span className="flex flex-wrap justify-end gap-1">
                                    {account.roles.map((role) => (
                                        <Badge key={role.id} variant="secondary">
                                            {role.name}
                                        </Badge>
                                    ))}
                                </span>
                            </button>
                            {index !== accounts.length - 1 && <Separator />}
                        </Fragment>
                    ))
                )}
            </CardFrame>
        </div>
    );
}
