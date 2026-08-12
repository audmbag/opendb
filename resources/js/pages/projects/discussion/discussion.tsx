import { ArchiveIcon, ChevronDownIcon, FileTextIcon, PencilIcon, TrashIcon } from 'lucide-react';
import Conversation from '@/components/ai/conversation';
import PromptInput from '@/components/ai/prompt-input';
import DropdownMenu from '@/components/dropdown-menu';
import Button from '@/components/form/button';
import { H6 } from '@/components/typograpfy/typography';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { useChatLayout } from '@/hooks/use-chat-layout';
import AppLayout from '@/layouts/app';
import ChatLayout from '@/layouts/chat-layout';

const ChatDiscussion = () => {

    const { dataPreviewOpen, toggleDataPreview } = useChatLayout();

    return (
        <>
            <div className="flex justify-between px-4 py-2">
                <div className="flex items-center gap-2">
                    <SidebarTrigger className="md:hidden" />
                    <H6>Comment créer une base de données</H6>
                    <DropdownMenu
                        items={[
                            {
                                label: 'Éditer',
                                icon: PencilIcon,
                            },
                            {
                                label: 'Archiver',
                                icon: ArchiveIcon,
                                separator: true,
                            },
                            {
                                label: 'Supprimer',
                                icon: TrashIcon,
                                destructive: true,
                            },
                        ]}
                    >
                        <Button
                            icon={ChevronDownIcon}
                            variant="ghost"
                            size="icon-sm"
                        />
                    </DropdownMenu>
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
            <div className="flex w-full flex-1 flex-col items-center overflow-auto p-4 pt-0">
                <Conversation />
                <PromptInput
                    suggestions={false}
                    className="[view-transition-name:prompt-input]"
                />
            </div>
        </>
    );
}


ChatDiscussion.layout = [AppLayout, ChatLayout];


export default ChatDiscussion;
