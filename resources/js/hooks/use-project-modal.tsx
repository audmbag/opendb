import { router, usePage } from '@inertiajs/react';
import React, { createContext, useCallback, useContext, useState } from 'react';
import { usePrivileges } from '@/hooks/use-privileges';
import HomeController from '@/wayfinder/actions/App/Http/Controllers/HomeController';

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

/**
 * A creation asked from a page of a project, where there is no creation modal: the home page opens it once loaded.
 * Kept outside of React, since the layout of the home page replaces the one of the project.
 */
let isCreationPending = false;

/**
 * Opens the creation of a project from anywhere: on the home page directly, from the others once back on it.
 */
export const useCreateProject = () => {
    const { setOpen } = useProjectModal();
    const { component } = usePage();
    const canCreate = usePrivileges().can('create_project');

    return useCallback(() => {
        if (!canCreate) {
            return;
        }

        if (component === 'home') {
            setOpen(true);

            return;
        }

        isCreationPending = true;
        router.visit(HomeController.url(), {
            onFinish: () => {
                isCreationPending = false;
            },
        });
    }, [canCreate, component, setOpen]);
};

export const ProjectModalProvider = ({
    children,
}: {
    children: React.ReactNode;
}) => {
    const [open, _setOpen_] = useState(() => isCreationPending);
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
