import { usePage } from '@inertiajs/react';
import React, { useEffect, useRef, useState } from 'react';
import { useDefaultLayout, usePanelRef } from 'react-resizable-panels';
import ChatSideBar from '@/components/chat/chat-side-bar';
import {
    ResizableHandle,
    ResizablePanel,
    ResizablePanelGroup,
} from '@/components/ui/resizable';
import { SidebarInset, SidebarProvider } from '@/components/ui/sidebar';
import { ChatLayoutProvider, useChatLayout } from '@/hooks/use-chat-layout';
import { cn } from '@/lib/utils';

const DATA_VIEWER_MAX_SIZE = '40%';

/**
 * During a resize, a layer over the panels shows the resize cursor and keeps the pointer away from their content.
 *
 * The library does both by itself, with a `*` cursor rule and `pointer-events: none` on the panels, but each of them
 * recalculates the style of the whole conversation when the resize starts and ends, and the cursor rule even when the
 * handle is only hovered. Its cursor is disabled on the group, and the content of each panel keeps its pointer events.
 */
const RESIZING_LAYER =
    'relative has-[>[data-separator=active]]:after:absolute has-[>[data-separator=active]]:after:inset-0 has-[>[data-separator=active]]:after:z-50 has-[>[data-separator=active]]:after:cursor-col-resize';

const Panels = ({ children }: { children: React.ReactNode }) => {
    const { component } = usePage();

    const { dataPreviewOpen, dataPreviewExpanded, setDataPreviewElement } = useChatLayout();

    /**
     * The artifacts come from the conversation: there is nothing to display on the other pages of the layout.
     */
    const showDataPreview = dataPreviewOpen && component === 'projects/conversation/conversation';

    /**
     * The data preview stays rendered after being closed, until its content has slid out.
     */
    const [dataPreviewRendered, setDataPreviewRendered] = useState(showDataPreview);

    if (showDataPreview && !dataPreviewRendered) {
        setDataPreviewRendered(true);
    }

    const dataPreviewClosing = dataPreviewRendered && !showDataPreview;

    const dataViewerRef = usePanelRef();

    /**
     * The width chosen by the user for the list of artifacts, given back when leaving an artifact.
     */
    const listSize = useRef<number | null>(null);

    const { defaultLayout, onLayoutChanged } = useDefaultLayout({
        id: 'project-chat-layout',
        storage: localStorage,
        panelIds: dataPreviewRendered ? ['chat', 'data-viewer'] : ['chat'],
    });

    useEffect(() => {
        const dataViewer = dataViewerRef.current;

        if (!dataViewer) {
            return;
        }

        if (dataPreviewExpanded) {
            listSize.current = dataViewer.getSize().asPercentage;
            dataViewer.resize(DATA_VIEWER_MAX_SIZE);

            return;
        }

        if (listSize.current !== null) {
            dataViewer.resize(`${listSize.current}%`);
            listSize.current = null;
        }
    }, [dataPreviewExpanded, dataViewerRef]);

    const handleDataPreviewAnimationEnd = (event: React.AnimationEvent<HTMLDivElement>) => {
        // The animations of the artifacts bubble up to here: only the slide of the data preview itself matters.
        if (event.target === event.currentTarget && dataPreviewClosing) {
            setDataPreviewRendered(false);
        }
    };

    return (
        <ResizablePanelGroup
            defaultLayout={defaultLayout}
            onLayoutChanged={dataPreviewExpanded ? undefined : onLayoutChanged}
            orientation="horizontal"
            disableCursor
            className={cn('w-full flex-1 gap-2', RESIZING_LAYER)}
        >
            <ResizablePanel
                className="pointer-events-auto overflow-hidden! rounded-lg border"
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
            {dataPreviewRendered && (
                <>
                    <ResizableHandle withHandle className="cursor-col-resize bg-transparent" />
                    <ResizablePanel
                        panelRef={dataViewerRef}
                        defaultSize="380px"
                        minSize="380px"
                        maxSize={DATA_VIEWER_MAX_SIZE}
                        id="data-viewer"
                        className="pointer-events-auto overflow-hidden!"
                    >
                        <div
                            ref={setDataPreviewElement}
                            onAnimationEnd={handleDataPreviewAnimationEnd}
                            className={cn(
                                'h-full duration-200 ease-out',
                                dataPreviewClosing
                                    ? 'animate-out fill-mode-forwards fade-out-0 slide-out-to-right'
                                    : 'animate-in fade-in-0 slide-in-from-right',
                            )}
                        />
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
