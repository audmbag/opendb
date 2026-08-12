import React from 'react';
import { useDefaultLayout } from 'react-resizable-panels';
import ChatSideBar from '@/components/chat/chat-side-bar';
import {
    ResizableHandle,
    ResizablePanel,
    ResizablePanelGroup,
} from '@/components/ui/resizable';
import { SidebarInset, SidebarProvider } from '@/components/ui/sidebar';
import { ChatLayoutProvider, useChatLayout } from '@/hooks/use-chat-layout';

const Panels = ({ children }: { children: React.ReactNode }) => {
    const { dataPreviewOpen } = useChatLayout();

    const { defaultLayout, onLayoutChanged } = useDefaultLayout({
        id: 'project-chat-layout',
        storage: localStorage,
        panelIds: dataPreviewOpen ? ['chat', 'data-viewer'] : ['chat'],
    });

    return (
        <ResizablePanelGroup
            defaultLayout={defaultLayout}
            onLayoutChanged={onLayoutChanged}
            orientation="horizontal"
            className="w-full flex-1 gap-2"
        >
            <ResizablePanel
                className="overflow-hidden! rounded-lg border"
                id="chat"
            >
                <div className="relative flex h-full w-full">
                    <ChatSideBar />
                    <SidebarInset>
                        <div className="flex flex-1 flex-col overflow-auto">
                            {children}
                        </div>
                    </SidebarInset>
                </div>
            </ResizablePanel>
            {dataPreviewOpen && (
                <>
                    <ResizableHandle withHandle className="bg-transparent" />
                    <ResizablePanel
                        defaultSize="380px"
                        minSize="380px"
                        maxSize="40%"
                        className="rounded-lg border"
                        id="data-viewer"
                    >
                        <div className="flex h-full items-center justify-center p-6">
                            <span className="font-semibold">Sidebar</span>
                        </div>
                    </ResizablePanel>
                </>
            )}
        </ResizablePanelGroup>
    );
};

export default function ChatLayout({ children }: { children: React.ReactNode }) {
    return (
        <SidebarProvider className="relative flex h-full min-h-auto w-full flex-col gap-2">
            <ChatLayoutProvider>
                <Panels>{children}</Panels>
            </ChatLayoutProvider>
        </SidebarProvider>
    );
}
