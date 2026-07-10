import { createContext, useContext, useState } from "react";
import { useHotkeys } from 'react-hotkeys-hook';


type CommandModalContextType = {
    open: boolean;
    setOpen: (open: boolean) => void;
};


const CommandModalContext = createContext<CommandModalContextType>({
    open: false,
    setOpen: () => {},
});



const CommandModalProvider = ({ children }: { children: React.ReactNode }) => {
    const [open, setOpen] = useState(false);

    useHotkeys('mod+k', (e) => {
        setOpen(prev => !prev);
    }, { preventDefault: true });

    return (
        <CommandModalContext.Provider value={{ open, setOpen }}>
            {children}
        </CommandModalContext.Provider>
    );
}


const useCommandModal = () => {
    
    const context = useContext(CommandModalContext);

    if (!context) {
        throw new Error("useCommandModal must be used within a CommandModalProvider");
    }
    return context;
}




export { CommandModalProvider, useCommandModal };