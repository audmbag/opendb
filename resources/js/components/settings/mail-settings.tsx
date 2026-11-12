import { useHttp } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import {
    LoaderIcon,
    PlugZapIcon,
    PlusIcon,
    SaveIcon,
    Trash2Icon,
    UsersIcon,
} from 'lucide-react';
import type React from 'react';
import { Fragment, useCallback, useEffect, useState } from 'react';
import { toast } from 'sonner';
import CardFrame from '@/components/card-frame';
import { EmptyState } from '@/components/empty-state';
import Button from '@/components/form/button';
import Input from '@/components/form/input';
import Select from '@/components/form/select';
import { Separator } from '@/components/ui/separator';
import type { APIData } from '@/types/global';
import type { MailRecipient, MailSetting } from '@/types/models/mail';
import MailRecipientController from '@/wayfinder/actions/App/Http/Controllers/MailRecipientController';
import MailSettingController from '@/wayfinder/actions/App/Http/Controllers/MailSettingController';

type SmtpForm = {
    host: string;
    port: string;
    encryption: 'tls' | 'ssl' | 'none';
    username: string;
    password: string;
    from_address: string;
    from_name: string;
};

type MessageResponse = {
    success?: boolean;
    message: string;
};

const SmtpSettings = () => {
    const { t } = useLaravelReactI18n();
    const encryptionItems = [
        { label: 'STARTTLS (port 587)', value: 'tls' },
        { label: 'SSL/TLS (port 465)', value: 'ssl' },
        { label: t('settings.mail.no_encryption'), value: 'none' },
    ];
    const [hasPassword, setHasPassword] = useState(false);
    const [action, setAction] = useState<'save' | 'test' | null>(null);

    const loader = useHttp<null, { data: MailSetting | null }>();
    const form = useHttp<SmtpForm, MessageResponse>({
        host: '',
        port: '587',
        encryption: 'tls',
        username: '',
        password: '',
        from_address: '',
        from_name: '',
    });

    const { setData, setDefaults, transform } = form;

    const fill = useCallback(
        (settings: MailSetting | null) => {
            if (!settings) {
                return;
            }

            const values: SmtpForm = {
                host: settings.host,
                port: String(settings.port),
                encryption: settings.encryption ?? 'none',
                username: settings.username ?? '',
                password: '',
                from_address: settings.from_address,
                from_name: settings.from_name ?? '',
            };

            setData(values);
            setDefaults(values);
            setHasPassword(settings.has_password);
        },
        [setData, setDefaults],
    );

    useEffect(() => {
        loader.get(MailSettingController.show.url(), {
            onSuccess: (response) => fill(response.data),
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    transform((data) => ({
        ...data,
        encryption: data.encryption === 'none' ? null : data.encryption,
    }));

    const save = (event: React.FormEvent) => {
        event.preventDefault();
        setAction('save');

        form.put(MailSettingController.update.url(), {
            onSuccess: (response) => {
                toast.success(response.message);
                setHasPassword(hasPassword || form.data.password !== '');
                setData('password', '');
            },
            onFinish: () => setAction(null),
        });
    };

    const testConnection = () => {
        setAction('test');

        form.post(MailSettingController.test.url(), {
            onSuccess: (response) => {
                toast[response.success ? 'success' : 'error'](
                    response.success
                        ? t('settings.mail.connection_succeeded')
                        : t('settings.mail.connection_failed'),
                    { description: response.message, duration: 10000 },
                );
            },
            onFinish: () => setAction(null),
        });
    };

    return (
        <CardFrame
            title={t('settings.mail.smtp_server')}
            description={t('settings.mail.smtp_server_description')}
        >
            {loader.processing ? (
                <div className="flex justify-center py-4">
                    <LoaderIcon className="animate-spin" />
                </div>
            ) : (
                <form className="flex flex-col gap-4" onSubmit={save}>
                    <div className="grid gap-4 sm:grid-cols-[1fr_8rem]">
                        <Input
                            label={t('settings.mail.host')}
                            name="host"
                            placeholder="smtp.gmail.com"
                            value={form.data.host}
                            onChange={(event) =>
                                setData('host', event.target.value)
                            }
                            error={form.errors.host}
                        />
                        <Input
                            label={t('settings.mail.port')}
                            name="port"
                            type="number"
                            value={form.data.port}
                            onChange={(event) =>
                                setData('port', event.target.value)
                            }
                            error={form.errors.port}
                        />
                    </div>
                    <Select
                        label={t('settings.mail.encryption')}
                        name="encryption"
                        value={form.data.encryption}
                        onValueChange={(value) =>
                            setData(
                                'encryption',
                                value as SmtpForm['encryption'],
                            )
                        }
                        items={encryptionItems}
                        error={form.errors.encryption}
                    />
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Input
                            label={t('settings.mail.username')}
                            name="username"
                            autoComplete="off"
                            value={form.data.username}
                            onChange={(event) =>
                                setData('username', event.target.value)
                            }
                            error={form.errors.username}
                        />
                        <Input
                            label={t('settings.mail.password')}
                            name="password"
                            type="password"
                            autoComplete="new-password"
                            placeholder={hasPassword ? t('settings.mail.unchanged') : undefined}
                            value={form.data.password}
                            onChange={(event) =>
                                setData('password', event.target.value)
                            }
                            error={form.errors.password}
                        />
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Input
                            label={t('settings.mail.from_address')}
                            name="from_address"
                            type="email"
                            placeholder={t('settings.mail.from_address_placeholder')}
                            value={form.data.from_address}
                            onChange={(event) =>
                                setData('from_address', event.target.value)
                            }
                            error={form.errors.from_address}
                        />
                        <Input
                            label={t('settings.mail.from_name')}
                            name="from_name"
                            value={form.data.from_name}
                            onChange={(event) =>
                                setData('from_name', event.target.value)
                            }
                            error={form.errors.from_name}
                        />
                    </div>
                    <div className="flex justify-end gap-2">
                        <Button
                            variant="secondary"
                            icon={PlugZapIcon}
                            onClick={testConnection}
                            processing={action === 'test'}
                            disabled={action !== null}
                        >
                            {t('settings.mail.test_connection')}
                        </Button>
                        <Button
                            type="submit"
                            icon={SaveIcon}
                            processing={action === 'save'}
                            disabled={action !== null}
                        >
                            {t('common.save')}
                        </Button>
                    </div>
                </form>
            )}
        </CardFrame>
    );
};

const MailRecipients = () => {
    const { t } = useLaravelReactI18n();
    const [recipients, setRecipients] = useState<MailRecipient[]>([]);
    const [deletingId, setDeletingId] = useState<number | undefined>();

    const loader = useHttp<null, APIData<MailRecipient>>();
    const remover = useHttp<null, { success: boolean }>();
    const form = useHttp<
        { email: string; name: string },
        { data: MailRecipient }
    >({
        email: '',
        name: '',
    });

    const load = useCallback(() => {
        loader.get(MailRecipientController.index.url(), {
            onSuccess: (response) => setRecipients(response.data),
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    useEffect(() => {
        load();
    }, [load]);

    const add = (event: React.FormEvent) => {
        event.preventDefault();

        form.post(MailRecipientController.store.url(), {
            onSuccess: () => {
                form.reset();
                load();
            },
        });
    };

    const remove = (recipient: MailRecipient) => {
        setDeletingId(recipient.id);

        remover.delete(MailRecipientController.destroy.url(recipient.id), {
            onSuccess: () =>
                setRecipients((current) =>
                    current.filter(({ id }) => id !== recipient.id),
                ),
            onFinish: () => setDeletingId(undefined),
        });
    };

    return (
        <CardFrame
            title={t('settings.mail.recipients')}
            description={t('settings.mail.recipients_description')}
            innerPadding={false}
        >
            <form className="flex items-start gap-2 p-3" onSubmit={add}>
                <Input
                    name="email"
                    type="email"
                    placeholder={t('settings.mail.recipient_email_placeholder')}
                    value={form.data.email}
                    onChange={(event) =>
                        form.setData('email', event.target.value)
                    }
                    error={form.errors.email}
                />
                <Input
                    name="name"
                    placeholder={t('settings.mail.recipient_name_placeholder')}
                    value={form.data.name}
                    onChange={(event) =>
                        form.setData('name', event.target.value)
                    }
                    error={form.errors.name}
                />
                <Button
                    type="submit"
                    icon={PlusIcon}
                    processing={form.processing}
                >
                    {t('common.add')}
                </Button>
            </form>
            <Separator />
            {loader.processing && recipients.length === 0 ? (
                <div className="flex justify-center py-4">
                    <LoaderIcon className="animate-spin" />
                </div>
            ) : recipients.length === 0 ? (
                <EmptyState
                    icon={UsersIcon}
                    title={t('settings.mail.no_recipients')}
                    description={t('settings.mail.no_recipients_description')}
                />
            ) : (
                recipients.map((recipient, index) => (
                    <Fragment key={recipient.id}>
                        <div className="flex items-center justify-between gap-2 px-3 py-2">
                            <div className="flex flex-col">
                                <span className="text-sm">
                                    {recipient.email}
                                </span>
                                {recipient.name && (
                                    <span className="text-xs text-muted-foreground">
                                        {recipient.name}
                                    </span>
                                )}
                            </div>
                            <Button
                                size="sm"
                                variant="ghost"
                                icon={Trash2Icon}
                                tooltip={t('settings.mail.remove')}
                                onClick={() => remove(recipient)}
                                processing={deletingId === recipient.id}
                                confirmation={{
                                    title: t('settings.mail.remove_recipient', { email: recipient.email }),
                                    description: t('settings.mail.remove_recipient_description'),
                                    icon: Trash2Icon,
                                }}
                            />
                        </div>
                        {index !== recipients.length - 1 && <Separator />}
                    </Fragment>
                ))
            )}
        </CardFrame>
    );
};

export default function MailSettings() {
    return (
        <div className="flex flex-col gap-4 pt-0.5">
            <MailRecipients />
            <SmtpSettings />
        </div>
    );
}
