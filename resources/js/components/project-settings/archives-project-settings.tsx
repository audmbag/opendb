import { router, useHttp, usePage } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import {
    ArchiveIcon,
    ArchiveRestoreIcon,
    ArrowUpRightIcon,
    LoaderIcon,
    SearchIcon,
    Trash2Icon,
} from 'lucide-react';
import { Fragment, useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';
import { formatRelativeTime } from '@/components/ai/artifacts/record/format';
import CardFrame from '@/components/card-frame';
import { EmptyState } from '@/components/empty-state';
import Button from '@/components/form/button';
import Input from '@/components/form/input';
import { Separator } from '@/components/ui/separator';
import { useProjectSettingsDialog } from '@/hooks/use-project-settings-modal';
import { useConversationStore } from '@/store/use-conversation-store';
import type { APIData } from '@/types/global';
import type { ArchivedConversation, Conversation } from '@/types/models/conversation';
import type { Project } from '@/types/models/project';
import ConversationController from '@/wayfinder/actions/App/Http/Controllers/ConversationController';
import ProjectArchivedConversationController from '@/wayfinder/actions/App/Http/Controllers/Project/ProjectArchivedConversationController';

const ArchivedConversationItem = ({
    conversation,
    restoring,
    deleting,
    onRestore,
    onOpen,
    onDelete,
}: {
    conversation: ArchivedConversation;
    restoring: boolean;
    deleting: boolean;
    onRestore: () => void;
    onOpen: () => void;
    onDelete: () => void;
}) => {
    const { t, tChoice } = useLaravelReactI18n();
    const untitled = t('conversations.untitled');
    const archivedAt = conversation.updated_at ? formatRelativeTime(conversation.updated_at) : null;

    return (
        <div className="flex items-center justify-between gap-3 px-3 py-2.5">
            <div className="min-w-0">
                <p className="truncate text-sm font-medium">
                    {conversation.title || untitled}
                </p>
                <div className="flex min-w-0 items-center gap-2 text-xs text-muted-foreground">
                    <span className="shrink-0">
                        {tChoice('conversations.messages', conversation.messages_count)}
                    </span>
                    {archivedAt && <span className="truncate">· {t('conversations.archived_at', { when: archivedAt })}</span>}
                </div>
            </div>
            <div className="flex shrink-0 items-center gap-1">
                <Button
                    variant="ghost"
                    size="icon-sm"
                    icon={ArrowUpRightIcon}
                    tooltip={t('conversations.restore_and_open')}
                    aria-label={t('conversations.restore_and_open')}
                    disabled={restoring || deleting}
                    onClick={onOpen}
                />
                <Button
                    variant="secondary"
                    size="sm"
                    icon={ArchiveRestoreIcon}
                    processing={restoring}
                    disabled={deleting}
                    onClick={onRestore}
                >
                    {t('conversations.restore')}
                </Button>
                <Button
                    variant="ghost"
                    size="icon-sm"
                    icon={Trash2Icon}
                    tooltip={t('common.delete')}
                    aria-label={t('common.delete')}
                    className="text-destructive hover:text-destructive"
                    processing={deleting}
                    disabled={restoring}
                    onClick={onDelete}
                    confirmation={{
                        title: t('conversations.delete_title'),
                        description: t('conversations.delete_archived_description', { title: conversation.title || untitled }),
                        icon: Trash2Icon,
                    }}
                />
            </div>
        </div>
    );
};

/**
 * The conversations of the project the user archived: restored, they come back in the list of the sidebar.
 */
export default function ArchivesProjectSettings() {
    const { t } = useLaravelReactI18n();
    const { props: { project } } = usePage<{ project: Project }>();
    const { close } = useProjectSettingsDialog();
    const addConversation = useConversationStore((state) => state.add);

    const [conversations, setConversations] = useState<ArchivedConversation[] | null>(null);
    const [search, setSearch] = useState('');
    const [pendingId, setPendingId] = useState<{ id: string; action: 'restore' | 'delete' } | null>(null);

    const loader = useHttp<null, APIData<ArchivedConversation>>();
    const restoreRequest = useHttp<null, { conversation: Conversation }>();
    const deleteRequest = useHttp<null, null>();
    const deleteAllRequest = useHttp<null, { message: string }>();

    useEffect(() => {
        loader.get(ProjectArchivedConversationController.index.url(project.id), {
            onSuccess: ({ data }) => setConversations(data),
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const shownConversations = useMemo(() => {
        const term = search.trim().toLowerCase();

        if (!term || !conversations) {
            return conversations ?? [];
        }

        return conversations.filter((conversation) =>
            (conversation.title ?? '').toLowerCase().includes(term),
        );
    }, [conversations, search]);

    const forget = (conversation: ArchivedConversation) =>
        setConversations((current) => current?.filter((item) => item.id !== conversation.id) ?? null);

    const restore = (conversation: ArchivedConversation, open = false) => {
        setPendingId({ id: conversation.id, action: 'restore' });

        restoreRequest.patch(ProjectArchivedConversationController.restore.url({ project: project.id, conversation: conversation.id }), {
            onSuccess: (response) => {
                forget(conversation);
                addConversation(response.conversation);

                if (open) {
                    close();
                    router.visit(
                        ConversationController.conversation.url({
                            project: conversation.project_id,
                            conversation: conversation.id,
                        }),
                        { viewTransition: true },
                    );

                    return;
                }

                toast.success(t('conversations.restored'), {
                    description: t('conversations.restored_description'),
                });
            },
            onError: () => toast.error(t('conversations.restore_failed')),
            onFinish: () => setPendingId(null),
        });
    };

    const remove = (conversation: ArchivedConversation) => {
        setPendingId({ id: conversation.id, action: 'delete' });

        deleteRequest.delete(ProjectArchivedConversationController.destroy.url({ project: project.id, conversation: conversation.id }), {
            onSuccess: () => {
                forget(conversation);
                toast.success(t('conversations.deleted'));
            },
            onError: () => toast.error(t('conversations.delete_failed')),
            onFinish: () => setPendingId(null),
        });
    };

    const removeAll = () => {
        deleteAllRequest.delete(ProjectArchivedConversationController.destroyAll.url(project.id), {
            onSuccess: (response) => {
                setConversations([]);
                toast.success(response.message);
            },
        });
    };

    if (conversations === null) {
        return (
            <div className="flex justify-center py-8">
                <LoaderIcon className="animate-spin" />
            </div>
        );
    }

    return (
        <div className="flex flex-col gap-4 pt-0.5">
            <CardFrame
                title={t('projects.settings.nav.archives')}
                description={t('conversations.archives_description')}
                icon={ArchiveIcon}
                innerPadding={false}
                actions={
                    conversations.length > 0 && (
                        <Button
                            variant="ghost"
                            size="sm"
                            icon={Trash2Icon}
                            className="text-destructive hover:text-destructive"
                            processing={deleteAllRequest.processing}
                            onClick={removeAll}
                            confirmation={{
                                title: t('conversations.delete_all_title'),
                                description: t('conversations.delete_all_description', { count: conversations.length }),
                                icon: Trash2Icon,
                            }}
                        >
                            {t('conversations.delete_all')}
                        </Button>
                    )
                }
            >
                {conversations.length === 0 ? (
                    <EmptyState
                        icon={ArchiveIcon}
                        title={t('conversations.no_archives')}
                        description={t('conversations.no_archives_description')}
                    />
                ) : (
                    <>
                        {conversations.length > 5 && (
                            <>
                                <div className="p-2">
                                    <Input
                                        icon={SearchIcon}
                                        placeholder={t('conversations.search_by_title')}
                                        value={search}
                                        onChange={(event) => setSearch(event.target.value)}
                                        aria-label={t('conversations.search_archived')}
                                    />
                                </div>
                                <Separator />
                            </>
                        )}
                        {shownConversations.map((conversation, index) => (
                            <Fragment key={conversation.id}>
                                <ArchivedConversationItem
                                    conversation={conversation}
                                    restoring={pendingId?.id === conversation.id && pendingId.action === 'restore'}
                                    deleting={pendingId?.id === conversation.id && pendingId.action === 'delete'}
                                    onRestore={() => restore(conversation)}
                                    onOpen={() => restore(conversation, true)}
                                    onDelete={() => remove(conversation)}
                                />
                                {index !== shownConversations.length - 1 && <Separator />}
                            </Fragment>
                        ))}
                        {shownConversations.length === 0 && (
                            <p className="px-3 py-6 text-center text-sm text-muted-foreground">
                                {t('conversations.no_archived_match', { search: search.trim() })}
                            </p>
                        )}
                    </>
                )}
            </CardFrame>
        </div>
    );
}
