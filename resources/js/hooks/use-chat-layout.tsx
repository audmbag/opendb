import React, { createContext, useCallback, useContext, useState } from 'react';
import { useSidebar } from '@/components/ui/sidebar';


type ChatLayoutContextType = {
    dataPreviewOpen: boolean;
    toggleDataPreview: () => void;
    /**
     * Whether the data preview takes its maximum width, to display the content of an artifact.
     */
    dataPreviewExpanded: boolean;
    setDataPreviewExpanded: (expanded: boolean) => void;
    /**
     * The element of the data preview, for the conversation to render its artifacts in. Null while it is closed.
     */
    dataPreviewElement: HTMLElement | null;
    setDataPreviewElement: (element: HTMLElement | null) => void;
}


const ChatLayoutContext = createContext<ChatLayoutContextType>({
    dataPreviewOpen: false,
    toggleDataPreview: () => {},
    dataPreviewExpanded: false,
    setDataPreviewExpanded: () => {},
    dataPreviewElement: null,
    setDataPreviewElement: () => {},
});


const ChatLayoutProvider = ({ children } : { children: React.ReactNode }) => {

    const [dataPreviewOpen, setDataPreviewOpen] = useState(false);
    const [dataPreviewExpanded, setDataPreviewExpanded] = useState(false);
    const [dataPreviewElement, setDataPreviewElement] = useState<HTMLElement | null>(null);

    const { open, setOpen } = useSidebar();

    const toggleDataPreview = useCallback(() => {
        const state = !dataPreviewOpen;
        setDataPreviewOpen(state);

        if(open && state) {
            setOpen(false);
        }

        if(!state && !open) {
            setOpen(true);
        }

    }, [dataPreviewOpen]);

    return (
        <ChatLayoutContext
            value={{
                dataPreviewOpen,
                toggleDataPreview,
                dataPreviewExpanded,
                setDataPreviewExpanded,
                dataPreviewElement,
                setDataPreviewElement,
            }}
        >
            {children}
        </ChatLayoutContext>
    );
}



const useChatLayout = () => {
    const context = useContext(ChatLayoutContext);

    if (!context) {
        throw new Error("useChatLayout must be used within a ChatLayoutProvider");
    }

    return context;
}




export { ChatLayoutProvider, useChatLayout };
