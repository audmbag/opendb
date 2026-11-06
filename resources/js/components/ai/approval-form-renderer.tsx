import { useHttp } from '@inertiajs/react';
import type { ToolUIPart } from 'ai';
import { FilePlus2Icon, Table2Icon, TriangleAlertIcon } from 'lucide-react';
import { useEffect, useState } from 'react';
import CardFrame from '@/components/card-frame';
import Button from '@/components/form/button';
import RecordFormFields, {
    initialRecordValues,
} from '@/components/record-form-fields';
import type { RecordValue } from '@/components/record-form-fields';
import { Badge } from '@/components/reui/badge';
import { Skeleton } from '@/components/ui/skeleton';
import type { RecordForm, RecordFormField } from '@/types/databases';
import TableRecordController from '@/wayfinder/actions/App/Http/Controllers/Chat/TableRecordController';

type Props = {
    part: ToolUIPart;
    projectId: string;
    conversationId: string;
    onApprovalResponded?: ({
        id,
        approved,
    }: {
        id: string;
        approved: boolean;
    }) => void;
};

type CreateRecordInput = {
    table?: string;
    known_values?: Record<string, unknown>;
};

export default function ApprovalFormRenderer({
    part,
    projectId,
    conversationId,
    onApprovalResponded,
}: Props) {
    const input = (part.input ?? {}) as CreateRecordInput;
    const table = input.table ?? '';

    const [fields, setFields] = useState<RecordFormField[] | null>(null);
    const [loadingError, setLoadingError] = useState<string | null>(null);
    const [submitError, setSubmitError] = useState<string | null>(null);

    const formRequest = useHttp<null, { data: RecordForm }>();
    const submitRequest = useHttp<Record<string, RecordValue>, unknown>({});

    useEffect(() => {
        formRequest
            .get(
                TableRecordController.form.url({ project: projectId, table }),
                {
                    onSuccess: ({ data }) => {
                        setFields(data.fields);
                        submitRequest.setData(
                            initialRecordValues(
                                data.fields,
                                input.known_values ?? {},
                            ),
                        );
                    },
                    onHttpException: () => {
                        setLoadingError(
                            `Impossible de charger la structure de la table « ${table} ».`,
                        );

                        return false;
                    },
                    onNetworkError: () => {
                        setLoadingError(
                            'Le serveur est injoignable, réessayez dans un instant.',
                        );

                        return false;
                    },
                },
            )
            .then(() => null);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [projectId, table]);

    const respond = (approved: boolean) =>
        onApprovalResponded?.({ id: part.approval!.id, approved });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        setSubmitError(null);

        submitRequest
            .post(
                TableRecordController.store.url({
                    conversation: conversationId,
                    toolCallId: part.toolCallId,
                }),
                {
                    onSuccess: () => respond(true),
                    onHttpException: () => {
                        setSubmitError(
                            "Cette demande n'attend plus de saisie : l'enregistrement n'a pas été soumis.",
                        );

                        return false;
                    },
                },
            )
            .then(() => null);
    };

    return (
        <CardFrame className="rounded-b-none">
            <form onSubmit={submit} className="flex flex-col gap-4">
                <div className="flex items-center gap-2 text-sm font-medium">
                    <FilePlus2Icon className="size-4 text-green-500" />
                    Nouvel enregistrement
                    <Badge variant="secondary" size="xl">
                        <Table2Icon className="size-4 text-muted-foreground" />
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
                    <RecordFormFields
                        fields={fields}
                        values={submitRequest.data}
                        errors={submitRequest.errors}
                        onChange={(field, value) =>
                            submitRequest.setData(field, value)
                        }
                    />
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
