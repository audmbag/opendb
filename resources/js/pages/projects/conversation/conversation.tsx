import { ArchiveIcon, ChevronDownIcon, FileTextIcon, Loader2, PencilIcon, TrashIcon } from 'lucide-react';
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
import { useChatLayout } from '@/hooks/use-chat-layout';
import { useConversation } from '@/hooks/use-conversation';
import AppLayout from '@/layouts/app';
import ChatLayout from '@/layouts/chat-layout';
import { useConversationStore } from '@/store/use-conversation-store';

const ChatDiscussion = () => {

    const { dataPreviewOpen, toggleDataPreview } = useChatLayout();

    const { conversation, archive, remove, update, updateConversationsRequest: { processing } } = useConversation();

    const [isEditingTitle, setIsEditingTitle] = useState(false);

    const [title, setTitle] = useState(conversation.title);

    const conversations = useConversationStore((state) => state.conversations)

    const handleEditTitle = useCallback(
        (value: string) => {
            setIsEditingTitle(false);

            if (value === title) {
                return;
            }

            update(conversation, { title: value }, () => {
                setTitle(conversation.title);
            });
        },
        [conversation, conversations],
    );

    return (
        <>
            <div className="flex justify-between pl-4 py-2 pr-6 absolute top-0 left-0 w-full z-10 bg-transparent">
                <div className="flex items-center gap-2">
                    <SidebarTrigger className="md:hidden" />
                    <Editable
                        value={title}
                        onValueChange={setTitle}
                        onSubmit={handleEditTitle}
                        placeholder="Titre de la conversation"
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

                    {!isEditingTitle && (
                        <DropdownMenu
                            items={[
                                {
                                    label: 'Éditer',
                                    icon: PencilIcon,
                                    onClick: () => setIsEditingTitle(true),
                                },
                                {
                                    label: 'Archiver',
                                    icon: ArchiveIcon,
                                    separator: true,
                                    onClick: () => archive(conversation),
                                },
                                {
                                    label: 'Supprimer',
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
                    icon={FileTextIcon}
                    variant="secondary"
                    size="icon-sm"
                    tooltip={
                        dataPreviewOpen
                            ? 'Masquer les données'
                            : 'Afficher les données'
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
