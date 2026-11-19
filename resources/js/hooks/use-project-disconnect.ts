import { router } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { UnplugIcon } from 'lucide-react';
import { useCallback, useState } from 'react';
import { toast } from 'sonner';
import AlertDialog from '@/components/alert-dialog';
import { projectEntryUrl, rememberLastProjectPage } from '@/lib/last-project-page';
import type { ProjectWork } from '@/lib/project-activity';
import {
    currentProjectWork,
    hasProjectWork,
    settleProjectWork,
    stopProjectWork,
} from '@/lib/project-activity';
import { useConversationStore } from '@/store/use-conversation-store';
import type { Project } from '@/types/models/project';
import ProjectDisconnectController from '@/wayfinder/actions/App/Http/Controllers/Project/ProjectDisconnectController';

/**
 * Leave the project: the user is told what they would lose, what can still be written is written, what runs on the
 * server is stopped, then they are back on the list of their projects.
 */
export function useProjectDisconnect(project: Project) {
    const { t, tChoice } = useLaravelReactI18n();
    const [processing, setProcessing] = useState(false);

    const describe = useCallback(
        (work: ProjectWork): string =>
            [
                ...work.changes.map(({ table, count }) =>
                    tChoice('projects.disconnection.changes', count, { tables: table }),
                ),
                work.unsavedFiles.length > 0
                    ? tChoice('projects.disconnection.unsaved_files', work.unsavedFiles.length, {
                          files: work.unsavedFiles.join(', '),
                      })
                    : null,
                work.runs.length > 0
                    ? tChoice('projects.disconnection.runs', work.runs.length)
                    : null,
                work.answering ? t('projects.disconnection.answer') : null,
            ]
                .filter(Boolean)
                .join(' '),
        [t, tChoice],
    );

    const disconnect = useCallback(async () => {
        const work = currentProjectWork();

        if (
            hasProjectWork(work) &&
            !(await AlertDialog.call({
                title: t('projects.disconnection.title', { project: project.name }),
                description: describe(work),
                icon: UnplugIcon,
                variant: 'destructive',
                confirmText: t('projects.disconnection.confirm'),
                cancelText: t('projects.disconnection.stay'),
            }))
        ) {
            return;
        }

        setProcessing(true);
        rememberLastProjectPage(project.id);

        await settleProjectWork();

        // Read again: a run may have ended while the dialog was open.
        const { runs } = currentProjectWork();

        stopProjectWork();
        router.cancelAll();

        router.post(
            ProjectDisconnectController.url(project.id),
            { runs },
            {
                onSuccess: () => {
                    const conversations = useConversationStore.getState();

                    conversations.fill([]);
                    conversations.clearPendingMessage();

                    // The page of the projects has its own toaster, mounted with it.
                    window.setTimeout(() =>
                        toast.success(
                            t('projects.disconnection.done', { project: project.name }),
                            {
                                action: {
                                    label: t('projects.disconnection.reopen'),
                                    onClick: () => router.visit(projectEntryUrl(project.id)),
                                },
                            },
                        ),
                    );
                },
                onHttpException: () => {
                    toast.error(t('projects.disconnection.failed'));
                },
                onNetworkError: () => {
                    toast.error(t('projects.disconnection.failed'));
                },
                onFinish: () => setProcessing(false),
            },
        );
    }, [describe, project.id, project.name, t]);

    return { disconnect, processing };
}
