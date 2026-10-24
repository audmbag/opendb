import { useHttp } from '@inertiajs/react';
import type { ToolUIPart } from 'ai';
import { FilePlus2Icon, Table2Icon, TriangleAlertIcon } from 'lucide-react';
import { useEffect, useState } from 'react';
import CardFrame from '@/components/card-frame';
import Button from '@/components/form/button';
import Input from '@/components/form/input';
import Select from '@/components/form/select';
import Switch from '@/components/form/switch';
import Textarea from '@/components/form/textarea';
import { Badge } from '@/components/reui/badge';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';
import type { RecordForm, RecordFormField } from '@/types/databases';
import TableRecordController from '@/wayfinder/actions/App/Http/Controllers/Chat/TableRecordController';

type RecordValue = string | boolean;

type Props = {
    part: ToolUIPart;
    projectId: string;
    conversationId: string;
    onApprovalResponded?: ({ id, approved }: { id: string; approved: boolean }) => void;
};

type CreateRecordInput = {
    table?: string;
    known_values?: Record<string, unknown>;
};

const INPUT_TYPES: Partial<Record<RecordFormField['type'], string>> = {
    integer: 'number',
    decimal: 'number',
    date: 'date',
    datetime: 'datetime-local',
    time: 'time',
};

/**
 * The value a field starts with: the one the agent already knows, else an empty value so the column default applies.
 */
const initialValue = (field: RecordFormField, knownValues: Record<string, unknown>): RecordValue => {
    const known = knownValues[field.name];

    if (field.type === 'boolean') {
        return known !== undefined ? Boolean(known) : ['1', 'true'].includes(String(field.default).toLowerCase());
    }

    if (known === undefined || known === null) {
        return '';
    }

    return typeof known === 'object' ? JSON.stringify(known) : String(known);
};

const fieldDescription = (field: RecordFormField): string =>
    [
        field.db_type,
        field.unique ? 'unique' : null,
        field.references ? `→ ${field.references.table}.${field.references.column}` : null,
    ]
        .filter(Boolean)
        .join(' · ');

export default function ApprovalFormRenderer({ part, projectId, conversationId, onApprovalResponded }: Props) {
    const input = (part.input ?? {}) as CreateRecordInput;
    const table = input.table ?? '';

    const [fields, setFields] = useState<RecordFormField[] | null>(null);
    const [loadingError, setLoadingError] = useState<string | null>(null);
    const [submitError, setSubmitError] = useState<string | null>(null);

    const formRequest = useHttp<null, { data: RecordForm }>();
    const submitRequest = useHttp<Record<string, RecordValue>, unknown>({});

    useEffect(() => {
        formRequest.get(TableRecordController.form.url({ project: projectId, table }), {
            onSuccess: ({ data }) => {
                setFields(data.fields);
                submitRequest.setData(
                    Object.fromEntries(data.fields.map((field) => [field.name, initialValue(field, input.known_values ?? {})])),
                );
            },
            onHttpException: () => {
                setLoadingError(`Impossible de charger la structure de la table « ${table} ».`);

                return false;
            },
            onNetworkError: () => {
                setLoadingError('Le serveur est injoignable, réessayez dans un instant.');

                return false;
            },
        }).then(() => null);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [projectId, table]);

    const respond = (approved: boolean) => onApprovalResponded?.({ id: part.approval!.id, approved });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        setSubmitError(null);

        submitRequest.post(TableRecordController.store.url({
            conversation: conversationId,
            toolCallId: part.toolCallId
        }), {
            onSuccess: () => respond(true),
            onHttpException: () => {
                setSubmitError("Cette demande n'attend plus de saisie : l'enregistrement n'a pas été soumis.");

                return false;
            },
        }).then(() => null);
    };

    const renderField = (field: RecordFormField) => {
        const value = submitRequest.data[field.name];
        const label = `${field.name}${field.required ? ' *' : ''}`;
        const error = submitRequest.errors[field.name];
        const placeholder = field.default !== null && field.default !== '' ? `Défaut : ${field.default}` : undefined;

        switch (field.type) {
            case 'boolean':
                return (
                    <Switch
                        key={field.name}
                        name={field.name}
                        label={label}
                        description={fieldDescription(field)}
                        error={error}
                        orientation="horizontal"
                        className="rounded-md border p-3"
                        checked={value === true}
                        onCheckedChange={(checked) => submitRequest.setData(field.name, checked)}
                    />
                );
            case 'enum':
                return (
                    <Select
                        key={field.name}
                        name={field.name}
                        label={label}
                        description={fieldDescription(field)}
                        error={error}
                        value={String(value)}
                        onValueChange={(selected) => submitRequest.setData(field.name, selected)}
                        items={(field.options ?? []).map((option) => ({ label: option, value: option }))}
                    />
                );
            case 'text':
            case 'json':
                return (
                    <Textarea
                        key={field.name}
                        name={field.name}
                        label={label}
                        description={fieldDescription(field)}
                        error={error}
                        placeholder={placeholder ?? (field.type === 'json' ? '{ }' : undefined)}
                        className={cn('sm:col-span-2', field.type === 'json' && '[&_textarea]:font-mono')}
                        value={String(value)}
                        onChange={(event) => submitRequest.setData(field.name, event.target.value)}
                    />
                );
            default:
                return (
                    <Input
                        key={field.name}
                        name={field.name}
                        label={label}
                        description={fieldDescription(field)}
                        error={error}
                        type={INPUT_TYPES[field.type] ?? 'text'}
                        step={field.type === 'decimal' ? 'any' : undefined}
                        maxLength={field.max_length ?? undefined}
                        placeholder={placeholder}
                        value={String(value)}
                        onChange={(event) => submitRequest.setData(field.name, event.target.value)}
                    />
                );
        }
    };

    return (
        <CardFrame className="rounded-b-none">
            <form onSubmit={submit} className="flex flex-col gap-4">
                <div className="flex items-center gap-2 text-sm font-medium">
                    <FilePlus2Icon className="size-4 text-green-500" />
                    Nouvel enregistrement
                    <Badge variant="secondary" size="xl">
                        <Table2Icon className="text-muted-foreground size-4" />
                        {table}
                    </Badge>
                </div>

                {(loadingError || submitError) && (
                    <p className="flex items-center gap-2 text-sm text-destructive">
                        <TriangleAlertIcon className="size-4" />
                        {loadingError ?? submitError}
                    </p>
                )}

                {!fields && !loadingError && (
                    <div className="grid gap-4 sm:grid-cols-2">
                        {Array.from({ length: 4 }, (_, index) => (
                            <div key={index} className="flex flex-col gap-2">
                                <Skeleton className="h-4 w-24" />
                                <Skeleton className="h-9 w-full" />
                            </div>
                        ))}
                    </div>
                )}

                {fields && (
                    <div className="grid gap-4 sm:grid-cols-2">
                        {fields.map(renderField)}
                    </div>
                )}

                <div className="flex items-center justify-end gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => respond(false)}
                        disabled={submitRequest.processing}
                    >
                        Annuler
                    </Button>
                    <Button
                        type="submit"
                        processing={submitRequest.processing}
                        disabled={!fields}
                    >
                        Enregistrer
                    </Button>
                </div>
            </form>
        </CardFrame>
    );
}
