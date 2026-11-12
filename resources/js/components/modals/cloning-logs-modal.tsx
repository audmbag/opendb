import { useHttp } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import type { LucideIcon } from 'lucide-react';
import {
    CircleCheckIcon,
    CircleXIcon,
    EraserIcon,
    FlagIcon,
    PlayIcon,
    ScrollTextIcon,
} from 'lucide-react';
import React, { useEffect, useRef, useState } from 'react';
import Alert from '@/components/alert';
import type {
    CloningLog,
    CloningResult,
} from '@/components/cloning/cloning-result.types';
import Modal from '@/components/modals/modal';
import { Badge } from '@/components/reui/badge';
import { Spinner } from '@/components/spinner';
import { Muted } from '@/components/typograpfy/typography';
import { dateFormatter, numberFormatter } from '@/lib/intl';
import { cn } from '@/lib/utils';
import ProjectController from '@/wayfinder/actions/App/Http/Controllers/ProjectController';

type Props = {
    projectId: string;
    logPath: string;
    onClose: () => void;
};

type LogLine = {
    icon: LucideIcon;
    tone: 'default' | 'success' | 'warning' | 'error';
    message: string;
    detail?: string | null;
};

type Translate = (key: string, replacements?: Record<string, string | number>) => string;

const NOT_FOUND_ERROR = 'cloning.ui.logs_not_found';
const UNEXPECTED_ERROR = 'cloning.ui.logs_failed';

const TONE_CLASSES: Record<LogLine['tone'], string> = {
    default: 'text-muted-foreground',
    success: 'text-emerald-600 dark:text-emerald-400',
    warning: 'text-amber-600 dark:text-amber-400',
    error: 'text-destructive',
};

const formatNumber = (value: number): string => numberFormatter().format(value);

const formatTime = (ts: string): string =>
    dateFormatter({ timeStyle: 'medium' }).format(new Date(ts));

const formatDuration = (seconds: number | null): string => {
    if (seconds === null) {
        return '—';
    }

    if (seconds < 60) {
        return `${numberFormatter({ maximumFractionDigits: 1 }).format(seconds)} s`;
    }

    return `${Math.floor(seconds / 60)} min ${Math.round(seconds % 60)} s`;
};

const describeLog = (log: CloningLog, t: Translate): LogLine => {
    switch (log.event) {
        case 'run_started':
            return {
                icon: PlayIcon,
                tone: 'default',
                message: t('cloning.ui.logs.run_started', { source: log.source, target: log.target }),
            };
        case 'table_transferred':
            return {
                icon: log.skipped > 0 ? CircleXIcon : CircleCheckIcon,
                tone: log.skipped > 0 ? 'warning' : 'success',
                message: t('cloning.ui.logs.table_transferred', { table: log.table, rows: formatNumber(log.rows) }),
                detail:
                    log.skipped > 0
                        ? t('cloning.ui.logs.rows_rejected', { rows: formatNumber(log.skipped) })
                        : null,
            };
        case 'table_transfer_failed':
            return {
                icon: CircleXIcon,
                tone: 'error',
                message: t('cloning.ui.logs.table_failed', { table: log.table }),
                detail: log.reason,
            };
        case 'run_finished':
            return {
                icon: FlagIcon,
                tone: log.success ? 'success' : 'error',
                message: log.success
                    ? t('cloning.ui.logs.run_finished', { rows: formatNumber(log.rows) })
                    : t('cloning.ui.logs.run_failed'),
            };
        case 'key_mapping_cleanup_completed':
            return {
                icon: EraserIcon,
                tone: 'default',
                message: t('cloning.ui.logs.key_mapping_cleanup'),
            };
    }
};

function Stat({
    label,
    value,
    className,
}: {
    label: string;
    value: string;
    className?: string;
}) {
    return (
        <div className="flex flex-col gap-0.5 rounded-md border border-border bg-muted/50 px-3 py-2">
            <span className="text-xs text-muted-foreground">{label}</span>
            <span className={cn('text-sm font-medium', className)}>
                {value}
            </span>
        </div>
    );
}

function CloningSummary({ result }: { result: CloningResult }) {
    const { t } = useLaravelReactI18n();
    const { stats } = result;

    return (
        <div className="flex flex-col gap-3">
            <div className="flex flex-wrap items-center gap-2">
                <Badge
                    variant={
                        result.status === 'success'
                            ? 'success-light'
                            : 'warning'
                    }
                >
                    {result.status === 'success' ? t('cloning.ui.success') : t('cloning.ui.warnings')}
                </Badge>
                {!result.complete && (
                    <Badge variant="destructive">{t('cloning.ui.incomplete')}</Badge>
                )}
                {result.started_at && (
                    <Muted>
                        {t('cloning.ui.started_at', { date: dateFormatter({ dateStyle: 'medium', timeStyle: 'short' }).format(new Date(result.started_at)) })}
                    </Muted>
                )}
            </div>
            <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
                <Stat label={t('cloning.ui.stats.duration')} value={formatDuration(result.duration)} />
                <Stat label={t('cloning.ui.stats.rows')} value={formatNumber(stats.rows)} />
                <Stat
                    label={t('cloning.ui.stats.tables_transferred')}
                    value={formatNumber(stats.tables_transferred)}
                />
                <Stat
                    label={t('cloning.ui.stats.tables_failed')}
                    value={formatNumber(stats.tables_failed)}
                    className={cn({
                        [TONE_CLASSES.error]: stats.tables_failed > 0,
                    })}
                />
                <Stat
                    label={t('cloning.ui.stats.tables_skipped')}
                    value={formatNumber(stats.tables_skipped)}
                />
                <Stat
                    label={t('cloning.ui.stats.rows_skipped')}
                    value={formatNumber(stats.rows_skipped)}
                    className={cn({
                        [TONE_CLASSES.warning]: stats.rows_skipped > 0,
                    })}
                />
                <Stat
                    label={t('cloning.ui.stats.warnings')}
                    value={formatNumber(stats.warnings)}
                    className={cn({
                        [TONE_CLASSES.warning]: stats.warnings > 0,
                    })}
                />
                <Stat
                    label={t('cloning.ui.stats.errors')}
                    value={formatNumber(stats.errors)}
                    className={cn({ [TONE_CLASSES.error]: stats.errors > 0 })}
                />
            </div>
        </div>
    );
}

function CloningLogList({ logs }: { logs: CloningLog[] }) {
    const { t } = useLaravelReactI18n();

    if (logs.length === 0) {
        return (
            <Muted className="p-6 text-center">
                {t('cloning.ui.logs_empty')}
            </Muted>
        );
    }

    return (
        <ul className="max-h-80 overflow-auto rounded-md border border-border bg-muted/30 font-mono text-xs">
            {logs.map((log, index) => {
                const { icon: Icon, tone, message, detail } = describeLog(log, t);

                return (
                    <li
                        key={index}
                        className="flex items-start gap-2 border-b border-border px-3 py-2 last:border-b-0"
                    >
                        <span className="shrink-0 text-muted-foreground">
                            {formatTime(log.ts)}
                        </span>
                        <Icon
                            className={cn(
                                'mt-px size-3.5 shrink-0',
                                TONE_CLASSES[tone],
                            )}
                        />
                        <div className="flex min-w-0 flex-col gap-0.5">
                            <span className="break-words">{message}</span>
                            {detail && (
                                <span
                                    className={cn(
                                        'break-all',
                                        TONE_CLASSES[tone],
                                    )}
                                >
                                    {detail}
                                </span>
                            )}
                        </div>
                    </li>
                );
            })}
        </ul>
    );
}

/**
 * Résultat et logs d'un clonage, lus dans son journal .jsonl au montage :
 * la boîte de dialogue n'est montée qu'à son ouverture.
 */
export default function CloningLogsModal({
    projectId,
    logPath,
    onClose,
}: Props) {
    const { t } = useLaravelReactI18n();
    const { get, processing } = useHttp<null, CloningResult>();

    const [result, setResult] = useState<CloningResult | null>(null);
    const [failure, setFailure] = useState<string | null>(null);

    const isMountedRef = useRef(false);

    useEffect(() => {
        if (isMountedRef.current) {
            return;
        }

        isMountedRef.current = true;

        get(
            ProjectController.cloningLogs.url(
                { project: projectId },
                { query: { path: logPath } },
            ),
            {
                onSuccess: (response) => setResult(response),
                onHttpException: (response) =>
                    setFailure(
                        response.status === 404
                            ? NOT_FOUND_ERROR
                            : UNEXPECTED_ERROR,
                    ),
                onNetworkError: () => setFailure(UNEXPECTED_ERROR),
            },
        ).catch(() => null);
    }, [get, projectId, logPath]);

    return (
        <Modal
            open
            showHeader
            onOpenChange={(open) => !open && onClose()}
            className="h-auto sm:max-w-2xl"
            title={t('cloning.ui.logs_title')}
            description={
                result?.source && result.target
                    ? `${result.source} → ${result.target}`
                    : undefined
            }
            icon={ScrollTextIcon}
        >
            <div className="flex flex-col gap-4 p-4">
                {failure ? (
                    <Alert
                        variant="destructive"
                        title={t('cloning.ui.logs_unavailable')}
                        message={t(failure)}
                    />
                ) : processing || result === null ? (
                    <div className="flex justify-center p-6">
                        <Spinner className="size-6" />
                    </div>
                ) : (
                    <>
                        <CloningSummary result={result} />
                        <CloningLogList logs={result.logs} />
                    </>
                )}
            </div>
        </Modal>
    );
}
