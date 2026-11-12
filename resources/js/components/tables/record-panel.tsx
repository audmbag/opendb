import { useLaravelReactI18n } from 'laravel-react-i18n';
import { CheckIcon, CopyIcon } from 'lucide-react';
import { useEffect, useState } from 'react';
import { describeLocation } from '@/components/ai/artifacts/record/format';
import type { RecordLocation } from '@/components/ai/artifacts/record/format';
import RecordBreadcrumb from '@/components/ai/artifacts/record/record-breadcrumb';
import RecordFields from '@/components/ai/artifacts/record/record-fields';
import RecordRelations from '@/components/ai/artifacts/record/record-relations';
import RecordSkeleton from '@/components/ai/artifacts/record/record-skeleton';
import Alert from '@/components/alert';
import Button from '@/components/form/button';
import type { InspectedRecord } from '@/types/agent';
import TableBrowserController from '@/wayfinder/actions/App/Http/Controllers/Tables/TableBrowserController';
import type { TableRequestError } from './api';
import { requestTableData } from './api';

type Props = {
    projectId: string;
    location: RecordLocation;
};

const COPIED_DURATION = 1600;

/**
 * A row with every column at full length, the rows it references and the rows referencing it, which open in place.
 */
export default function RecordPanel({ projectId, location }: Props) {
    const { t } = useLaravelReactI18n();
    const [stack, setStack] = useState<RecordLocation[]>([location]);
    const [syncedLocation, setSyncedLocation] = useState(location);
    const [record, setRecord] = useState<{
        location: string;
        data: InspectedRecord;
    } | null>(null);
    const [failure, setFailure] = useState<{
        location: string;
        message: string;
    } | null>(null);
    const [hideNull, setHideNull] = useState(false);
    const [copied, setCopied] = useState(false);

    // Another row was opened from the grid: its relations start again from it.
    if (describeLocation(location) !== describeLocation(syncedLocation)) {
        setSyncedLocation(location);
        setStack([location]);
    }

    const current = stack[stack.length - 1];
    const currentKey = describeLocation(current);

    useEffect(() => {
        const controller = new AbortController();

        requestTableData<InspectedRecord>(
            'get',
            TableBrowserController.record.url(
                {
                    project: projectId,
                    table: encodeURIComponent(current.table),
                },
                {
                    query: {
                        key: current.key as Record<string, string | number>,
                    },
                },
            ),
            { signal: controller.signal },
        )
            .then((data) => setRecord({ location: currentKey, data }))
            .catch(
                (exception: TableRequestError) =>
                    !exception.cancelled &&
                    setFailure({
                        location: currentKey,
                        message: exception.first(),
                    }),
            );

        return () => controller.abort();
        // The key of the location is its identity: a new object for the same row must not read it again.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [projectId, currentKey]);

    const copyAsJson = async () => {
        if (record) {
            await navigator.clipboard.writeText(
                JSON.stringify(record.data.values, null, 2),
            );
            setCopied(true);
            setTimeout(() => setCopied(false), COPIED_DURATION);
        }
    };

    const open = (next: RecordLocation) => setStack([...stack, next]);
    const loaded = record?.location === currentKey ? record.data : null;
    const error = failure?.location === currentKey ? failure.message : null;

    return (
        <div className="flex min-h-0 flex-1 flex-col">
            {stack.length > 1 && (
                <RecordBreadcrumb
                    stack={stack}
                    onNavigate={(depth) => setStack(stack.slice(0, depth + 1))}
                />
            )}

            {error ? (
                <Alert
                    variant="destructive"
                    title={t('tables.record.read_failed')}
                    message={error}
                    className="m-4 w-auto"
                />
            ) : !loaded ? (
                <RecordSkeleton />
            ) : (
                <div className="min-h-0 flex-1 overflow-y-auto">
                    <div className="flex justify-end px-4 pt-3">
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={copyAsJson}
                        >
                            {copied ? <CheckIcon /> : <CopyIcon />}
                            {copied ? t('common.copied') : t('ai.record.copy_json')}
                        </Button>
                    </div>
                    <section aria-label={t('ai.record.columns')} className="@container">
                        <RecordFields
                            key={currentKey}
                            record={loaded}
                            hideNull={hideNull}
                            onHideNullChange={setHideNull}
                            onOpen={open}
                        />
                    </section>
                    <section aria-label={t('ai.record.relations')} className="border-t">
                        <RecordRelations record={loaded} onOpen={open} />
                    </section>
                </div>
            )}
        </div>
    );
}
