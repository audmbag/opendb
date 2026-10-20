import { router, useHttp, usePage } from '@inertiajs/react';
import {
    ArrowRightIcon,
    CopyIcon,
    ScrollTextIcon,
    SettingsIcon,
} from 'lucide-react';
import { motion } from 'motion/react';
import React, { useEffect, useRef, useState } from 'react';
import Alert from '@/components/alert';
import type { CloningResultMinimalResult } from '@/components/cloning/cloning-result.types';
import type { CloningPayload } from '@/components/cloning/cloning.types';
import DataTransferAnimation from '@/components/cloning/data-transfer-animation';
import type { TransferStatus } from '@/components/cloning/data-transfer-animation';
import Button from '@/components/form/button';
import CloningLogsModal from '@/components/modals/cloning-logs-modal';
import Modal from '@/components/modals/modal';
import { Muted, Small } from '@/components/typograpfy/typography';
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
const UNEXPECTED_ERROR =
    'Une erreur inattendue est survenue, veuillez réessayer.';

const STATUS_TEXTS: Record<
    TransferStatus,
    { title: string; description: string; message: string }
> = {
    transferring: {
        title: 'Clonage en cours',
        description: 'La base de données est en cours de clonage',
        message:
            'Cette opération peut prendre plusieurs minutes, ne fermez pas cette fenêtre.',
    },
    success: {
        title: 'Clonage terminé',
        description: 'La base de données a été clonée',
        message: 'Toutes les données ont été transférées.',
    },
    warning: {
        title: 'Clonage terminé avec des avertissements',
        description: 'La base de données a été clonée partiellement',
        message: "Certaines données n'ont pas pu être transférées.",
    },
    error: {
        title: 'Clonage échoué',
        description: "La base de données n'a pas pu être clonée",
        message: 'Le clonage a été interrompu.',
    },
};

export default function CloningProgressModal({
    source,
    payload,
    onCancel,
}: Props) {
    const tempProjectId = useTempProjectStore((state) => state.tmpProject?.id);
    const project = usePage<{ project?: Project }>().props.project;
    const projectId = tempProjectId || project?.id;

    const { post } = useHttp<any, CloningResultMinimalResult>(payload);

    const [failures, setFailures] = useState<string[] | null>(null);
    const [status, setStatus] = useState<TransferStatus>('transferring');
    const [logsOpen, setLogsOpen] = useState(false);
    const [logPath, setLogPath] = useState<string | null>(null);

    const isMountedRef = useRef(false);

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
                setFailures([UNEXPECTED_ERROR]);
                setStatus('error');
            },
            onNetworkError: () => {
                setFailures([UNEXPECTED_ERROR]);
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
                    failures ? 'Clonage interrompu' : STATUS_TEXTS[status].title
                }
                description={
                    failures
                        ? "Le clonage n'a pas pu être lancé"
                        : STATUS_TEXTS[status].description
                }
                icon={CopyIcon}
            >
                {failures ? (
                    <div className="flex flex-col gap-4 p-4">
                        <Alert
                            variant="destructive"
                            title="Les données du clonage ont été refusées"
                            message={failures[0] ?? UNEXPECTED_ERROR}
                        />
                        {failures.length > 1 && (
                            <ul className="list-disc space-y-1 pl-5 text-sm text-muted-foreground">
                                {failures
                                    .slice(1, MAX_DISPLAYED_ERRORS)
                                    .map((failure, index) => (
                                        <li key={index}>{failure}</li>
                                    ))}
                                {failures.length > MAX_DISPLAYED_ERRORS && (
                                    <li>{`… et ${failures.length - MAX_DISPLAYED_ERRORS} autre(s) erreur(s)`}</li>
                                )}
                            </ul>
                        )}
                        <Button
                            variant="outline"
                            icon={SettingsIcon}
                            onClick={onCancel}
                        >
                            Modifier la configuration
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
                                ? `Clonage vers « ${payload.cloningDriverConfig.database} »…`
                                : `Destination : « ${payload.cloningDriverConfig.database} »`}
                        </Small>
                        <Muted>{STATUS_TEXTS[status].message}</Muted>
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
                                Voir les logs
                            </Button>
                        )}
                        {status === 'error' ? (
                            <Button
                                variant="outline"
                                icon={SettingsIcon}
                                onClick={onCancel}
                            >
                                Modifier la configuration
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
                                Ouvrir le projet
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
