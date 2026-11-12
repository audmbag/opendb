import { useLaravelReactI18n } from 'laravel-react-i18n';
import {
    InfoIcon,
    MessageSquareTextIcon,
    TriangleAlertIcon,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import TableProfile from '@/components/ai/artifacts/table-profile/table-profile';
import TableProfileSkeleton from '@/components/ai/artifacts/table-profile/table-profile-skeleton';
import Alert from '@/components/alert';
import Button from '@/components/form/button';
import type { TableProfile as TableProfileData } from '@/types/agent';
import TableAssistantController from '@/wayfinder/actions/App/Http/Controllers/Tables/TableAssistantController';
import type { TableRequestError } from '../api';
import { requestTableData } from '../api';
import type { TableInsights } from '../types';
import { useTableAssistant } from './use-table-assistant';

type Props = {
    projectId: string;
    table: string;
    disabledReason: string | null;
    onAskChat: (question: string) => void;
};

/**
 * The profile of the columns, computed by the database at once, and what the assistant reads in it when asked.
 */
export default function InsightsPanel({
    projectId,
    table,
    disabledReason,
    onAskChat,
}: Props) {
    const { t } = useLaravelReactI18n();
    const routeArgs = { project: projectId, table: encodeURIComponent(table) };
    const [profile, setProfile] = useState<{
        table: string;
        data: TableProfileData;
    } | null>(null);
    const [profileFailure, setProfileFailure] = useState<{
        table: string;
        message: string;
    } | null>(null);
    const { ask, result, error, processing, reset } =
        useTableAssistant<TableInsights>();
    const [askedFor, setAskedFor] = useState<string | null>(null);

    if (askedFor !== null && askedFor !== table) {
        setAskedFor(null);
        reset();
    }

    useEffect(() => {
        const controller = new AbortController();

        requestTableData<TableProfileData>(
            'get',
            TableAssistantController.profile.url(routeArgs),
            { signal: controller.signal },
        )
            .then((data) => setProfile({ table, data }))
            .catch(
                (exception: TableRequestError) =>
                    !exception.cancelled &&
                    setProfileFailure({ table, message: exception.first() }),
            );

        return () => controller.abort();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [projectId, table]);

    const profileError =
        profileFailure?.table === table ? profileFailure.message : null;

    const explain = () => {
        setAskedFor(table);
        ask(TableAssistantController.insights.url(routeArgs));
    };

    return (
        <div className="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto p-4">
            <section className="flex flex-col gap-3 rounded-lg border p-3">
                <div className="flex items-start justify-between gap-3">
                    <div className="flex flex-col gap-0.5">
                        <h3 className="text-sm font-medium">
                            {t('tables.assistant.reading')}
                        </h3>
                        <p className="text-sm text-muted-foreground">
                            {t('tables.assistant.insights_description')}
                        </p>
                    </div>
                    <Button
                        size="sm"
                        variant={result ? 'outline' : 'default'}
                        onClick={explain}
                        disabled={processing || disabledReason !== null}
                        tooltip={disabledReason ?? undefined}
                        processing={processing}
                    >
                        {result ? t('tables.assistant.rerun') : t('tables.assistant.explain_table')}
                    </Button>
                </div>

                {disabledReason && (
                    <Alert
                        variant="warning"
                        title={t('tables.assistant.unavailable')}
                        message={disabledReason}
                    />
                )}
                {error && (
                    <Alert
                        variant="destructive"
                        title={t('tables.assistant.no_answer')}
                        message={error}
                    />
                )}
                {processing && !result && (
                    <p className="text-sm text-muted-foreground">
                        {t('tables.assistant.reading_profile')}
                    </p>
                )}

                {result && askedFor === table && (
                    <div className="flex flex-col gap-4 text-sm">
                        <p className="leading-relaxed">{result.summary}</p>

                        {result.anomalies.length > 0 && (
                            <ul className="flex flex-col gap-2">
                                {result.anomalies.map((anomaly, index) => (
                                    <li
                                        key={index}
                                        className="flex items-start gap-2"
                                    >
                                        {anomaly.severity === 'warning' ? (
                                            <TriangleAlertIcon
                                                className="mt-0.5 size-4 shrink-0 text-warning"
                                                aria-label={t('tables.assistant.to_check')}
                                            />
                                        ) : (
                                            <InfoIcon
                                                className="mt-0.5 size-4 shrink-0 text-muted-foreground"
                                                aria-label={t('tables.assistant.note')}
                                            />
                                        )}
                                        <span>
                                            {anomaly.column && (
                                                <span className="font-mono text-xs">
                                                    {anomaly.column}{' '}
                                                </span>
                                            )}
                                            {anomaly.issue}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}

                        {result.columns.length > 0 && (
                            <dl className="grid grid-cols-[minmax(0,10rem)_1fr] gap-x-3 gap-y-1.5">
                                {result.columns.map((column) => (
                                    <div key={column.name} className="contents">
                                        <dt className="truncate font-mono text-xs leading-5 text-muted-foreground">
                                            {column.name}
                                        </dt>
                                        <dd>{column.meaning}</dd>
                                    </div>
                                ))}
                            </dl>
                        )}

                        {result.questions.length > 0 && (
                            <div className="flex flex-col gap-1.5">
                                <span className="text-xs text-muted-foreground">
                                    {t('tables.assistant.to_dig')}
                                </span>
                                {result.questions.map((question) => (
                                    <button
                                        key={question}
                                        type="button"
                                        onClick={() => onAskChat(question)}
                                        className="flex items-start gap-2 rounded-md px-2 py-1.5 text-left transition-colors hover:bg-muted"
                                    >
                                        <MessageSquareTextIcon className="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                                        {question}
                                    </button>
                                ))}
                            </div>
                        )}
                    </div>
                )}
            </section>

            {profileError ? (
                <Alert
                    variant="destructive"
                    title={t('tables.assistant.profile_failed')}
                    message={profileError}
                />
            ) : profile?.table === table ? (
                <TableProfile profile={profile.data} />
            ) : (
                <TableProfileSkeleton />
            )}
        </div>
    );
}
