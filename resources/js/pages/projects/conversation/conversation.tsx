import { Head } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import {
    ArchiveIcon,
    ChevronDownIcon,
    Loader2,
    PencilIcon,
    ShapesIcon,
    TrashIcon,
} from 'lucide-react';
import { useCallback, useState } from 'react';
import Conversation from '@/components/ai/conversation';
import DropdownMenu from '@/components/dropdown-menu';
import Button from '@/components/form/button';
import { H6 } from '@/components/typograpfy/typography';
import {
    Editable,
    EditableArea,
    EditableInput, EditablePreview,
} from '@/components/ui/editable';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { Skeleton } from '@/components/ui/skeleton';
import { useChatLayout } from '@/hooks/use-chat-layout';
import { useConversation } from '@/hooks/use-conversation';
import { useConversationTitle } from '@/hooks/use-conversation-title';
import AppLayout from '@/layouts/app';
import ChatLayout from '@/layouts/chat-layout';

const ChatDiscussion = () => {
    const { t } = useLaravelReactI18n();
    const { dataPreviewOpen, toggleDataPreview } = useChatLayout();

    const { conversation, archive, remove, update, updateConversationsRequest: { processing } } = useConversation();

    const [isEditingTitle, setIsEditingTitle] = useState(false);

    /**
     * Null while the queue generates the title of a conversation just started.
     */
    const generatedTitle = useConversationTitle(conversation);

    const [title, setTitle] = useState(generatedTitle ?? '');

    const [shownTitle, setShownTitle] = useState(generatedTitle);

    if (generatedTitle !== shownTitle) {
        setShownTitle(generatedTitle);

        if (generatedTitle && !isEditingTitle) {
            setTitle(generatedTitle);
        }
    }

    const handleEditTitle = useCallback(
        (value: string) => {
            setIsEditingTitle(false);

            if (value === title) {
                return;
            }

            update(conversation, { title: value }, () => {
                setTitle(generatedTitle ?? '');
            });
        },
        [conversation, title, generatedTitle, update],
    );

    return (
        <>
            <Head title={generatedTitle ?? t('conversations.new_conversation')} />
            <div className="absolute top-0 left-0 z-10 flex w-full justify-between bg-background/5 py-2 pr-6 pl-4 backdrop-blur-xs">
                <div className="flex items-center gap-2">
                    <SidebarTrigger className="md:hidden" aria-label={t('common.toggle_sidebar')} />
                    {generatedTitle === null ? (
                        <Skeleton
                            className="h-5 w-48"
                            aria-label={t('conversations.generating_title')}
                        />
                    ) : (
                    <Editable
                        value={title}
                        onValueChange={setTitle}
                        onSubmit={handleEditTitle}
                        placeholder={t('conversations.title_placeholder')}
                        autosize
                        editing={isEditingTitle}
                    >
                        <EditableArea>
                            <H6>
                                <EditablePreview />
                            </H6>
                            <EditableInput
                                className="px-1"
                                autoComplete="off"
                            />
                        </EditableArea>
                    </Editable>
                    )}

                    {!isEditingTitle && generatedTitle !== null && (
                        <DropdownMenu
                            items={[
                                {
                                    label: t('common.edit'),
                                    icon: PencilIcon,
                                    separator: true,
                                    onClick: () => setIsEditingTitle(true),
                                },
                                {
                                    label: t('conversations.archive'),
                                    icon: ArchiveIcon,
                                    destructive: true,
                                    onClick: () => archive(conversation),
                                },
                                {
                                    label: t('common.delete'),
                                    icon: TrashIcon,
                                    destructive: true,
                                    onClick: () => remove(conversation),
                                },
                            ]}
                        >
                            <Button
                                icon={ChevronDownIcon}
                                variant="ghost"
                                size="icon-sm"
                            />
                        </DropdownMenu>
                    )}
                    {processing && <Loader2 className="size-4 animate-spin" />}
                </div>
                <Button
                    icon={ShapesIcon}
                    variant="secondary"
                    size="icon-sm"
                    tooltip={
                        dataPreviewOpen
                            ? t('conversations.hide_artifacts')
                            : t('conversations.show_artifacts')
                    }
                    onClick={toggleDataPreview}
                />
            </div>
            <div className="flex w-full flex-1 flex-col items-center overflow-auto">
                <Conversation key={conversation?.id} />
            </div>
        </>
    );
}


ChatDiscussion.layout = [AppLayout, ChatLayout];


export default ChatDiscussion;
