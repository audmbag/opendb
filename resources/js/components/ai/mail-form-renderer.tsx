import { useHttp } from '@inertiajs/react';
import type { ToolUIPart } from 'ai';
import { MailIcon, SendIcon, TriangleAlertIcon } from 'lucide-react';
import type React from 'react';
import { Fragment, useEffect, useMemo, useState } from 'react';
import MailAttachments from '@/components/ai/mail-attachments';
import CardFrame from '@/components/card-frame';
import Button from '@/components/form/button';
import Input from '@/components/form/input';
import Textarea from '@/components/form/textarea';
import {
    Combobox,
    ComboboxChip,
    ComboboxChips,
    ComboboxChipsInput,
    ComboboxContent,
    ComboboxEmpty,
    ComboboxItem,
    ComboboxList,
    ComboboxValue,
    useComboboxAnchor,
} from '@/components/ui/combobox';
import { Field, FieldLabel } from '@/components/ui/field';
import type { APIData } from '@/types/global';
import type { MailRecipient, MailSetting } from '@/types/models/mail';
import MailFormController from '@/wayfinder/actions/App/Http/Controllers/Chat/MailFormController';
import MailRecipientController from '@/wayfinder/actions/App/Http/Controllers/MailRecipientController';
import MailSettingController from '@/wayfinder/actions/App/Http/Controllers/MailSettingController';

type Props = {
    part: ToolUIPart;
    conversationId: string;
    onApprovalResponded?: ({ id, approved }: { id: string; approved: boolean }) => void;
};

type MailForm = {
    to: string[];
    subject: string;
    body: string;
    attachments: string[];
};

type SendMailInput = Partial<MailForm>;

/**
 * An address the user may pick: an allowed recipient, or the sender address of the user.
 */
type RecipientOption = {
    email: string;
    name: string | null;
};

const stringList = (value: unknown): string[] =>
    Array.isArray(value) ? [...new Set(value.map(String).filter((item) => item.trim() !== ''))] : [];

/**
 * Replace the literal `\n` (and `\r\n`) sequences a model sometimes writes instead of real line breaks.
 */
const withRealLineBreaks = (text: string): string => text.replace(/\\r\\n|\\n/g, '\n');

/**
 * The first error of a list field: the one of the field itself, else the one of one of its items (`to.0`, `to.1`…).
 */
const listError = (errors: Record<string, string>, field: string): string | undefined =>
    errors[field] ?? Object.entries(errors).find(([key]) => key.startsWith(`${field}.`))?.[1];

export default function MailFormRenderer({ part, conversationId, onApprovalResponded }: Props) {
    const input = (part.input ?? {}) as SendMailInput;
    const proposedAttachments = useMemo(() => stringList(input.attachments), [input.attachments]);

    const anchor = useComboboxAnchor();
    const [settings, setSettings] = useState<MailSetting | null>(null);
    const [recipients, setRecipients] = useState<MailRecipient[]>([]);
    const [disabledAttachments, setDisabledAttachments] = useState<string[]>([]);
    const [submitError, setSubmitError] = useState<string | null>(null);

    const recipientsRequest = useHttp<null, APIData<MailRecipient>>();
    const settingsRequest = useHttp<null, { data: MailSetting | null }>();

    const form = useHttp<MailForm, unknown>({
        to: stringList(input.to).map((email) => email.toLowerCase()),
        subject: input.subject ?? '',
        body: withRealLineBreaks(input.body ?? ''),
        attachments: proposedAttachments,
    });

    useEffect(() => {
        settingsRequest.get(MailSettingController.show.url(), {
            onSuccess: ({ data }) => setSettings(data),
        }).then(() => null);
        recipientsRequest.get(MailRecipientController.index.url(), {
            onSuccess: ({ data }) => setRecipients(data),
        }).then(() => null);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const options = useMemo<RecipientOption[]>(() => {
        const sender = settings ? [{ email: settings.from_address.toLowerCase(), name: settings.from_name ?? 'Vous' }] : [];

        return [
            ...sender,
            ...recipients
                .filter(({ email }) => email !== sender[0]?.email)
                .map(({ email, name }) => ({ email, name })),
        ];
    }, [settings, recipients]);

    /**
     * Every address the combobox knows: the allowed ones, plus the ones the agent proposed so they still show as chips.
     */
    const items = useMemo(
        () => [...new Set([...options.map(({ email }) => email), ...form.data.to])],
        [options, form.data.to],
    );

    const labelOf = (email: string): string => {
        const name = options.find((option) => option.email === email)?.name;

        return name ? `${name} <${email}>` : email;
    };

    const errors = form.errors as Record<string, string>;
    const generalError = submitError ?? errors.settings;
    const loadingRecipients = settingsRequest.processing || recipientsRequest.processing;

    form.transform((data) => ({
        ...data,
        attachments: data.attachments.filter((path) => !disabledAttachments.includes(path)),
    }));

    const respond = (approved: boolean) => onApprovalResponded?.({ id: part.approval!.id, approved });

    const toggleAttachment = (path: string, enabled: boolean) =>
        setDisabledAttachments((current) => (enabled ? current.filter((item) => item !== path) : [...current, path]));

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        setSubmitError(null);

        form.post(MailFormController.store.url({ conversation: conversationId, toolCallId: part.toolCallId }), {
            onSuccess: () => respond(true),
            onHttpException: () => {
                setSubmitError("Ce mail n'attend plus de validation : il n'a pas été envoyé.");

                return false;
            },
        }).then(() => null);
    };

    return (
        <CardFrame className="rounded-b-none">
            <form onSubmit={submit} className="flex flex-col gap-4">
                <div className="flex items-center gap-2 text-sm font-medium">
                    <MailIcon className="size-4" />
                    Nouveau mail
                </div>

                {generalError && (
                    <p className="flex items-center gap-2 text-sm text-destructive">
                        <TriangleAlertIcon className="size-4" />
                        {generalError}
                    </p>
                )}

                <Field className="gap-2">
                    <FieldLabel htmlFor="to">Destinataires</FieldLabel>
                    <Combobox
                        multiple
                        autoHighlight
                        items={items}
                        value={form.data.to}
                        onValueChange={(value: string[]) => form.setData('to', value)}
                        itemToStringLabel={labelOf}
                    >
                        <ComboboxChips ref={anchor} className="w-full" aria-invalid={!!listError(errors, 'to')}>
                            <ComboboxValue>
                                {(values: string[]) => (
                                    <Fragment>
                                        {values.map((email) => (
                                            <ComboboxChip key={email}>{labelOf(email)}</ComboboxChip>
                                        ))}
                                        <ComboboxChipsInput
                                            id="to"
                                            placeholder={loadingRecipients ? 'Chargement des destinataires…' : 'Ajouter un destinataire autorisé…'}
                                            aria-invalid={!!listError(errors, 'to')}
                                        />
                                    </Fragment>
                                )}
                            </ComboboxValue>
                        </ComboboxChips>
                        <ComboboxContent anchor={anchor}>
                            <ComboboxEmpty>
                                Aucun destinataire autorisé. Ajoutez-en dans Réglages &gt; Messagerie.
                            </ComboboxEmpty>
                            <ComboboxList>
                                {(email: string) => (
                                    <ComboboxItem key={email} value={email}>
                                        {labelOf(email)}
                                    </ComboboxItem>
                                )}
                            </ComboboxList>
                        </ComboboxContent>
                    </Combobox>
                    {listError(errors, 'to') && (
                        <p className="text-sm text-destructive">{listError(errors, 'to')}</p>
                    )}
                </Field>

                <Input
                    label="Objet"
                    name="subject"
                    value={form.data.subject}
                    error={errors.subject}
                    onChange={(event) => form.setData('subject', event.target.value)}
                />

                <Textarea
                    label="Message"
                    name="body"
                    rows={6}
                    value={form.data.body}
                    error={errors.body}
                    onChange={(event) => form.setData('body', event.target.value)}
                />

                {proposedAttachments.length > 0 && (
                    <MailAttachments
                        conversationId={conversationId}
                        toolCallId={part.toolCallId}
                        disabled={disabledAttachments}
                        onToggle={toggleAttachment}
                        onLoaded={(files) => setDisabledAttachments(files.filter(({ error }) => error !== null).map(({ path }) => path))}
                        error={listError(errors, 'attachments')}
                    />
                )}

                <div className="flex items-center justify-end gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => respond(false)}
                        disabled={form.processing}
                    >
                        Annuler
                    </Button>
                    <Button
                        type="submit"
                        icon={SendIcon}
                        processing={form.processing}
                        disabled={form.data.to.length === 0}
                    >
                        Envoyer
                    </Button>
                </div>
            </form>
        </CardFrame>
    );
}
