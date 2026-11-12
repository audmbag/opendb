import { useLaravelReactI18n } from 'laravel-react-i18n';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import Alert from '@/components/alert';
import Button from '@/components/form/button';
import RecordFormFields, {
    initialRecordValues,
} from '@/components/record-form-fields';
import type { RecordValue } from '@/components/record-form-fields';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Skeleton } from '@/components/ui/skeleton';
import type { RecordForm, RecordFormField } from '@/types/databases';
import TableRecordController from '@/wayfinder/actions/App/Http/Controllers/Chat/TableRecordController';
import TableRecordsController from '@/wayfinder/actions/App/Http/Controllers/Tables/TableRecordsController';
import type { TableRequestError } from './api';
import { requestTableData } from './api';

type Props = {
    projectId: string;
    table: string;
    /**
     * Values to start from, such as the row being duplicated.
     */
    initialValues?: Record<string, unknown>;
    onInserted: (values: Record<string, unknown>) => void;
    onClose: () => void;
};

/**
 * A row added through a form built from the columns of the table and checked by the server before it is written,
 * unlike a row added in the grid, which waits with the other changes.
 */
export default function InsertRecordPanel({
    projectId,
    table,
    initialValues,
    onInserted,
    onClose,
}: Props) {
    const { t } = useLaravelReactI18n();
    const [fields, setFields] = useState<RecordFormField[] | null>(null);
    const [values, setValues] = useState<Record<string, RecordValue>>({});
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [loadingError, setLoadingError] = useState<string | null>(null);
    const [processing, setProcessing] = useState(false);
    const [keepOpen, setKeepOpen] = useState(false);

    useEffect(() => {
        const controller = new AbortController();

        requestTableData<RecordForm>(
            'get',
            TableRecordController.form.url({
                project: projectId,
                table: encodeURIComponent(table),
            }),
            { signal: controller.signal },
        )
            .then((form) => {
                setFields(form.fields);
                setValues(initialRecordValues(form.fields, initialValues));
            })
            .catch(
                (exception: TableRequestError) =>
                    !exception.cancelled && setLoadingError(exception.first()),
            );

        return () => controller.abort();
        // The values to start from are read once, when the form opens.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [projectId, table]);

    const submit = async (event: React.FormEvent) => {
        event.preventDefault();
        setProcessing(true);
        setErrors({});

        try {
            const inserted = await requestTableData<{
                values: Record<string, unknown>;
            }>(
                'post',
                TableRecordsController.store.url({
                    project: projectId,
                    table: encodeURIComponent(table),
                }),
                { data: values },
            );

            toast.success(t('tables.insert.added'), { duration: 2000 });
            onInserted(inserted.values);

            if (keepOpen && fields) {
                setValues(initialRecordValues(fields));
            } else {
                onClose();
            }
        } catch (exception) {
            const error = exception as TableRequestError;

            setErrors(
                Object.fromEntries(
                    Object.entries(error.errors).map(([field, messages]) => [
                        field,
                        messages[0],
                    ]),
                ),
            );

            if (Object.keys(error.errors).length === 0 || error.errors.query) {
                toast.error(t('tables.insert.failed'), {
                    description: error.first(),
                });
            }
        } finally {
            setProcessing(false);
        }
    };

    if (loadingError) {
        return (
            <Alert
                variant="destructive"
                title={t('tables.insert.form_failed')}
                message={loadingError}
                className="m-4 w-auto"
            />
        );
    }

    return (
        <form onSubmit={submit} className="flex min-h-0 flex-1 flex-col">
            <div className="min-h-0 flex-1 overflow-y-auto p-4">
                {fields ? (
                    fields.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            {t('tables.insert.all_defaults')}
                        </p>
                    ) : (
                        <RecordFormFields
                            fields={fields}
                            values={values}
                            errors={errors}
                            onChange={(field, value) =>
                                setValues((current) => ({
                                    ...current,
                                    [field]: value,
                                }))
                            }
                        />
                    )
                ) : (
                    <div className="flex flex-col gap-4">
                        {Array.from({ length: 5 }, (_, index) => (
                            <div key={index} className="flex flex-col gap-2">
                                <Skeleton className="h-4 w-24" />
                                <Skeleton className="h-9 w-full" />
                            </div>
                        ))}
                    </div>
                )}
            </div>
            <div className="flex flex-wrap items-center gap-3 border-t px-4 py-3">
                <Label className="mr-auto flex items-center gap-2 text-sm font-normal">
                    <Checkbox
                        checked={keepOpen}
                        onCheckedChange={(checked) =>
                            setKeepOpen(checked === true)
                        }
                    />
                    {t('tables.insert.add_another')}
                </Label>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={onClose}
                    disabled={processing}
                >
                    {t('common.cancel')}
                </Button>
                <Button
                    type="submit"
                    size="sm"
                    disabled={!fields || processing}
                    processing={processing}
                >
                    {t('tables.insert.add')}
                </Button>
            </div>
        </form>
    );
}
