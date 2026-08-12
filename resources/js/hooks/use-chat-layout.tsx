import React, { createContext, useCallback, useContext, useEffect, useState } from 'react';
import { useSidebar } from '@/components/ui/sidebar';


type ChatLayoutContextType = {
    dataPreviewOpen: boolean;
    toggleDataPreview: () => void;
}


const ChatLayoutContext = createContext<ChatLayoutContextType>({
    dataPreviewOpen: false,
    toggleDataPreview: () => {},
});


const ChatLayoutProvider = ({ children } : { children: React.ReactNode }) => {

    const [dataPreviewOpen, setDataPreviewOpen] = useState(false);

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
        <ChatLayoutContext value={{ dataPreviewOpen, toggleDataPreview }}>
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
