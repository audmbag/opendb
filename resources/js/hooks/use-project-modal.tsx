import React, { createContext, useContext, useState } from 'react';

type ProjectModalContextType = {
    open: boolean;
    setOpen: (open: boolean) => void;
    cloningOpen: boolean;
    setCloningOpen: (open: boolean) => void;
};

const ProjectModalContext = createContext<ProjectModalContextType>({
    open: false,
    setOpen: () => null,
    cloningOpen: false,
    setCloningOpen: () => null,
});

export const useProjectModal = () => {
    const context = useContext(ProjectModalContext);

    if(!context) {
        throw new Error('useProjectModal must be used within a ProjectModalProvider');
    }

    return context;
};

export const ProjectModalProvider = ({
    children,
}: {
    children: React.ReactNode;
}) => {
    const [open, setOpen] = useState(false);
    const [cloningOpen, setCloningOpen] = useState(false);

    return (
        <ProjectModalContext.Provider
            value={{ open, setOpen, cloningOpen, setCloningOpen }}
        >
            {children}
        </ProjectModalContext.Provider>
    );
};
