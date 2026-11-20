import { router, useHttp, usePage } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import {
    ArrowRightIcon,
    BanIcon,
    CopyIcon,
    ScrollTextIcon,
    SettingsIcon,
} from 'lucide-react';
import { motion } from 'motion/react';
import React, { useEffect, useRef, useState } from 'react';
import Alert from '@/components/alert';
import CloningProgressDetails, { formatElapsed } from '@/components/cloning/cloning-progress-details';
import type { CloningResultMinimalResult } from '@/components/cloning/cloning-result.types';
import type { CloningPayload } from '@/components/cloning/cloning.types';
import DataTransferAnimation from '@/components/cloning/data-transfer-animation';
import type { TransferStatus } from '@/components/cloning/data-transfer-animation';
import { useCloningProgress } from '@/components/cloning/use-cloning-progress';
import Button from '@/components/form/button';
import CloningLogsModal from '@/components/modals/cloning-logs-modal';
import Modal from '@/components/modals/modal';
import { Muted, Small } from '@/components/typograpfy/typography';
import { trans } from '@/lib/i18n';
import { useTempProjectStore } from '@/store/use-project-store';
import type { DriverConfig } from '@/types/databases';
import type { Project } from '@/types/models/project';
import ConversationController from '@/wayfinder/actions/App/Http/Controllers/ConversationController';
import ProjectController from '@/wayfinder/actions/App/Http/Controllers/ProjectController';

type Props = {
    source: DriverConfig;
    payload: CloningPayload;
    onCancel: () => void;
};

const MAX_DISPLAYED_ERRORS = 5;

const STATUS_TEXTS: Record<
    TransferStatus,
    { title: string; description: string; message: string }
> = {
    transferring: {
        title: 'cloning.ui.status.transferring.title',
        description: 'cloning.ui.status.transferring.description',
        message: 'cloning.ui.status.transferring.message',
    },
    success: {
        title: 'cloning.ui.status.success.title',
        description: 'cloning.ui.status.success.description',
        message: 'cloning.ui.status.success.message',
    },
    warning: {
        title: 'cloning.ui.status.warning.title',
        description: 'cloning.ui.status.warning.description',
        message: 'cloning.ui.status.warning.message',
    },
    error: {
        title: 'cloning.ui.status.error.title',
        description: 'cloning.ui.status.error.description',
        message: 'cloning.ui.status.error.message',
    },
    cancelled: {
        title: 'cloning.ui.status.cancelled.title',
        description: 'cloning.ui.status.cancelled.description',
        message: 'cloning.ui.status.cancelled.message',
    },
};

export default function CloningProgressModal({
    source,
    payload,
    onCancel,
}: Props) {
    const { t } = useLaravelReactI18n();
    const tempProjectId = useTempProjectStore((state) => state.tmpProject?.id);
    const project = usePage<{ project?: Project }>().props.project;
    const projectId = tempProjectId || project?.id;

    const { post } = useHttp<any, CloningResultMinimalResult>(payload);

    const [failures, setFailures] = useState<string[] | null>(null);
    const [status, setStatus] = useState<TransferStatus>('transferring');
    const [logsOpen, setLogsOpen] = useState(false);
    const [logPath, setLogPath] = useState<string | null>(null);

    const isMountedRef = useRef(false);
    const running = status === 'transferring' && failures === null;
    const { progress, elapsedSeconds, cancel, isCancelling } = useCloningProgress(
        projectId,
        payload.anonymisation.cloningId,
        running,
    );

    useEffect(() => {
        if (isMountedRef.current || !projectId) {
            return;
        }

        isMountedRef.current = true;

        post(ProjectController.saveWithCloning.url({ project: projectId }), {
            onSuccess: (response) => {
                setLogPath(response.logPath);
                setStatus(response.status);
            },
            onError: (errors) => {
                setFailures(Object.values(errors).map(String));
                setStatus('error');
            },
            onHttpException: () => {
                setFailures([trans('common.unexpected_error')]);
                setStatus('error');
            },
            onNetworkError: () => {
                setFailures([trans('common.unexpected_error')]);
                setStatus('error');
            },
        }).catch(() => null);
    }, [post, projectId]);

    return (
        <>
            <Modal
                open
                showHeader
                showCloseButton={status !== 'transferring'}
                className="h-auto sm:max-w-md"
                title={
                    failures ? t('cloning.ui.interrupted') : t(STATUS_TEXTS[status].title)
                }
                description={
                    failures
                        ? t('cloning.ui.not_started')
                        : t(STATUS_TEXTS[status].description)
                }
                icon={CopyIcon}
            >
                {failures ? (
                    <div className="flex flex-col gap-4 p-4">
                        <Alert
                            variant="destructive"
                            title={t('cloning.ui.rejected')}
                            message={failures[0] ?? t('common.unexpected_error')}
                        />
                        {failures.length > 1 && (
                            <ul className="list-disc space-y-1 pl-5 text-sm text-muted-foreground">
                                {failures
                                    .slice(1, MAX_DISPLAYED_ERRORS)
                                    .map((failure, index) => (
                                        <li key={index}>{failure}</li>
                                    ))}
                                {failures.length > MAX_DISPLAYED_ERRORS && (
                                    <li>{t('cloning.ui.more_errors', { count: failures.length - MAX_DISPLAYED_ERRORS })}</li>
                                )}
                            </ul>
                        )}
                        <Button
                            variant="outline"
                            icon={SettingsIcon}
                            onClick={onCancel}
                        >
                            {t('cloning.ui.edit_configuration')}
                        </Button>
                    </div>
                ) : (
                    <div className="flex flex-col items-center gap-4 p-6 text-center">
                        <DataTransferAnimation
                            source={{
                                driverId: source.driver_id,
                                database: source.database,
                            }}
                            destination={{
                                driverId: payload.cloningDriverConfig.driver_id,
                                database: payload.cloningDriverConfig.database,
                            }}
                            status={status}
                        />
                        <Small>
                            {status === 'transferring'
                                ? t('cloning.ui.cloning_to', { database: payload.cloningDriverConfig.database })
                                : t('cloning.ui.destination', { database: payload.cloningDriverConfig.database })}
                        </Small>
                        {running ? (
                            <CloningProgressDetails progress={progress} elapsedSeconds={elapsedSeconds} />
                        ) : (
                            elapsedSeconds > 0 && (
                                <Muted className="tabular-nums">
                                    {t('cloning.ui.progress.duration', { time: formatElapsed(elapsedSeconds) })}
                                </Muted>
                            )
                        )}
                        <Muted>{t(STATUS_TEXTS[status].message)}</Muted>
                    </div>
                )}

                {running && (
                    <div className="flex justify-end border-t border-border bg-accent p-4">
                        <Button
                            variant="outline"
                            icon={BanIcon}
                            processing={isCancelling}
                            onClick={cancel}
                            confirmation={{
                                title: t('cloning.ui.cancel_title'),
                                description: t('cloning.ui.cancel_description'),
                                icon: BanIcon,
                                confirmText: t('cloning.ui.cancel'),
                                cancelText: t('cloning.ui.keep_cloning'),
                            }}
                        >
                            {isCancelling ? t('cloning.ui.cancelling') : t('cloning.ui.cancel')}
                        </Button>
                    </div>
                )}

                {!failures && status !== 'transferring' && projectId && (
                    <motion.div
                        className="flex items-center justify-between gap-2.5 border-t border-border bg-accent p-4"
                        initial={{ opacity: 0, y: 8 }}
                        animate={{ opacity: 1, y: 0 }}
                        transition={{
                            duration: 0.3,
                            ease: 'easeOut',
                            delay: 0.4,
                        }}
                    >
                        {logPath && (
                            <Button
                                variant="outline"
                                icon={ScrollTextIcon}
                                onClick={() => setLogsOpen(true)}
                            >
                                {t('cloning.ui.see_logs')}
                            </Button>
                        )}
                        {status === 'error' || status === 'cancelled' ? (
                            <Button
                                variant="outline"
                                icon={SettingsIcon}
                                onClick={onCancel}
                            >
                                {t('cloning.ui.edit_configuration')}
                            </Button>
                        ) : (
                            <Button
                                icon={ArrowRightIcon}
                                className="flex-row-reverse"
                                onClick={() =>
                                    router.visit(
                                        ConversationController.home.url({
                                            project: projectId,
                                        }),
                                    )
                                }
                            >
                                {t('projects.open')}
                            </Button>
                        )}
                    </motion.div>
                )}
            </Modal>

            {logsOpen && logPath && projectId && (
                <CloningLogsModal
                    projectId={projectId}
                    logPath={logPath}
                    onClose={() => setLogsOpen(false)}
                />
            )}
        </>
    );
}
