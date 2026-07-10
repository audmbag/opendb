import React, { createContext, useContext, useState } from "react";
import { useHotkeys } from "react-hotkeys-hook";


type SettingsContextType = {
    open: boolean;
    setOpen: (open: boolean) => void;
}


const SettingsContext = createContext<SettingsContextType>({
    open: false,
    setOpen: () => { },
})


const SettingsProvider = ({ children } : { children: React.ReactNode }) => {

    const [open, setOpen] = useState(false);

    useHotkeys("mod+,", () => {
        setOpen(prev => !prev);
    }, { preventDefault: true, useKey: true, delimiter: "|" });
    

    return <SettingsContext.Provider value={{ open, setOpen }}>
        {children}
    </SettingsContext.Provider>
}



const useSettingsDialog = () => {
    const context = useContext(SettingsContext);
    if (!context) {
        throw new Error("useSettingsDialog must be used within a SettingsProvider");
    }
    return context;
}




export { SettingsProvider, useSettingsDialog };