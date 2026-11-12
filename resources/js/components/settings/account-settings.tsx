import { router, useHttp, usePage } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { KeyRoundIcon, SaveIcon, UserIcon } from 'lucide-react';
import type React from 'react';
import { toast } from 'sonner';
import Alert from '@/components/alert';
import CardFrame from '@/components/card-frame';
import Button from '@/components/form/button';
import Input from '@/components/form/input';
import SettingsController from '@/wayfinder/actions/App/Http/Controllers/SettingsController';

type AccountForm = {
    name: string;
    current_password: string;
    password: string;
    password_confirmation: string;
};

/**
 * The name the user logs in with, and their password.
 */
export default function AccountSettings() {
    const { t } = useLaravelReactI18n();
    const { auth, appName } = usePage().props;

    const form = useHttp<AccountForm, { message: string }>({
        name: auth.user.name,
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const isDefaultAccount = auth.user.name === 'opendb';

    const save = (event: React.FormEvent) => {
        event.preventDefault();

        form.put(SettingsController.updateAccount.url(), {
            onSuccess: (response) => {
                toast.success(response.message);
                form.setDefaults({
                    ...form.data,
                    current_password: '',
                    password: '',
                    password_confirmation: '',
                });
                form.reset(
                    'current_password',
                    'password',
                    'password_confirmation',
                );
                router.reload({ only: ['auth'] });
            },
        });
    };

    return (
        <form className="flex flex-col gap-4" onSubmit={save}>
            {isDefaultAccount && (
                <Alert
                    variant="warning"
                    title={t('settings.account.default_account')}
                    message={t('settings.account.default_account_message', { app: appName })}
                />
            )}
            <CardFrame
                title={t('settings.account.identity')}
                description={t('settings.account.identity_description')}
                icon={UserIcon}
            >
                <Input
                    label={t('settings.account.username')}
                    name="name"
                    autoComplete="username"
                    value={form.data.name}
                    onChange={(event) =>
                        form.setData('name', event.target.value)
                    }
                    error={form.errors.name}
                />
            </CardFrame>
            <CardFrame
                title={t('settings.account.password')}
                description={t('settings.account.password_description')}
                icon={KeyRoundIcon}
            >
                <div className="flex flex-col gap-4">
                    <Input
                        label={t('settings.account.current_password')}
                        name="current_password"
                        type="password"
                        autoComplete="current-password"
                        value={form.data.current_password}
                        onChange={(event) =>
                            form.setData('current_password', event.target.value)
                        }
                        error={form.errors.current_password}
                    />
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Input
                            label={t('settings.account.new_password')}
                            name="password"
                            type="password"
                            autoComplete="new-password"
                            value={form.data.password}
                            onChange={(event) =>
                                form.setData('password', event.target.value)
                            }
                            error={form.errors.password}
                        />
                        <Input
                            label={t('settings.account.password_confirmation')}
                            name="password_confirmation"
                            type="password"
                            autoComplete="new-password"
                            value={form.data.password_confirmation}
                            onChange={(event) =>
                                form.setData(
                                    'password_confirmation',
                                    event.target.value,
                                )
                            }
                        />
                    </div>
                </div>
            </CardFrame>
            <div className="flex justify-end">
                <Button
                    type="submit"
                    icon={SaveIcon}
                    processing={form.processing}
                    disabled={!form.isDirty}
                >
                    {t('common.save')}
                </Button>
            </div>
        </form>
    );
}
