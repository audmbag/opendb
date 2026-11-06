import { useHttp } from '@inertiajs/react';
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

const encryptionItems = [
    { label: 'STARTTLS (port 587)', value: 'tls' },
    { label: 'SSL/TLS (port 465)', value: 'ssl' },
    { label: 'Aucun', value: 'none' },
];

const SmtpSettings = () => {
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
                        ? 'Connexion réussie'
                        : 'Échec de la connexion',
                    { description: response.message, duration: 10000 },
                );
            },
            onFinish: () => setAction(null),
        });
    };

    return (
        <CardFrame
            title="Serveur SMTP"
            description="Les identifiants utilisés par l'agent pour envoyer des mails en votre nom. Ils sont chiffrés et ne sont jamais transmis au modèle."
        >
            {loader.processing ? (
                <div className="flex justify-center py-4">
                    <LoaderIcon className="animate-spin" />
                </div>
            ) : (
                <form className="flex flex-col gap-4" onSubmit={save}>
                    <div className="grid gap-4 sm:grid-cols-[1fr_8rem]">
                        <Input
                            label="Hôte"
                            name="host"
                            placeholder="smtp.gmail.com"
                            value={form.data.host}
                            onChange={(event) =>
                                setData('host', event.target.value)
                            }
                            error={form.errors.host}
                        />
                        <Input
                            label="Port"
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
                        label="Chiffrement"
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
                            label="Nom d'utilisateur"
                            name="username"
                            autoComplete="off"
                            value={form.data.username}
                            onChange={(event) =>
                                setData('username', event.target.value)
                            }
                            error={form.errors.username}
                        />
                        <Input
                            label="Mot de passe"
                            name="password"
                            type="password"
                            autoComplete="new-password"
                            placeholder={hasPassword ? 'Inchangé' : undefined}
                            value={form.data.password}
                            onChange={(event) =>
                                setData('password', event.target.value)
                            }
                            error={form.errors.password}
                        />
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Input
                            label="Adresse d'expéditeur"
                            name="from_address"
                            type="email"
                            placeholder="vous@exemple.com"
                            value={form.data.from_address}
                            onChange={(event) =>
                                setData('from_address', event.target.value)
                            }
                            error={form.errors.from_address}
                        />
                        <Input
                            label="Nom d'expéditeur"
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
                            Tester la connexion
                        </Button>
                        <Button
                            type="submit"
                            icon={SaveIcon}
                            processing={action === 'save'}
                            disabled={action !== null}
                        >
                            Enregistrer
                        </Button>
                    </div>
                </form>
            )}
        </CardFrame>
    );
};

const MailRecipients = () => {
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
            title="Destinataires autorisés"
            description="L'agent ne peut envoyer de mails qu'à ces adresses. Il peut aussi en ajouter ou en retirer, toujours avec votre autorisation."
            innerPadding={false}
        >
            <form className="flex items-start gap-2 p-3" onSubmit={add}>
                <Input
                    name="email"
                    type="email"
                    placeholder="adresse@exemple.com"
                    value={form.data.email}
                    onChange={(event) =>
                        form.setData('email', event.target.value)
                    }
                    error={form.errors.email}
                />
                <Input
                    name="name"
                    placeholder="Nom (facultatif)"
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
                    Ajouter
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
                    title="Aucun destinataire autorisé"
                    description="Ajoutez les adresses auxquelles l'agent peut envoyer des mails."
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
                                tooltip="Retirer"
                                onClick={() => remove(recipient)}
                                processing={deletingId === recipient.id}
                                confirmation={{
                                    title: `Retirer ${recipient.email}`,
                                    description:
                                        "L'agent ne pourra plus envoyer de mails à cette adresse.",
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
