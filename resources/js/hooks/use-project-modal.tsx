import React, { createContext, useContext, useState } from 'react';

type ProjectModalContextType = {
    open: boolean;
    setOpen: (open: boolean, step?: number) => void;
    cloningOpen: boolean;
    setCloningOpen: (open: boolean) => void;
    step: number;
    setStep: (step: number | ((prev: number) => number)) => void;
};

const ProjectModalContext = createContext<ProjectModalContextType>({
    open: false,
    setOpen: () => null,
    cloningOpen: false,
    setCloningOpen: () => null,
    setStep: () => null,
    step: 1,
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
    const [open, _setOpen_] = useState(false);
    const [cloningOpen, setCloningOpen] = useState(false);
    const [step, setStep] = useState(1);

    const setOpen = (open: boolean, step: number = 1) => {
        _setOpen_(open);
        setStep(step);
    }

    return (
        <ProjectModalContext.Provider
            value={{ open, setOpen, cloningOpen, setCloningOpen, step, setStep }}
        >
            {children}
        </ProjectModalContext.Provider>
    );
};
