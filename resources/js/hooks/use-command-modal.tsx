import {
    createContext,
    useCallback,
    useContext,
    useMemo,
    useState,
} from 'react';
import { useHotkeys } from 'react-hotkeys-hook';

/**
 * The lists of the palette: the commands, then the choices of the commands ending with « ... ».
 */
export type PalettePage =
    'root' | 'projects' | 'theme' | 'color-theme' | 'language';

type CommandModalContextType = {
    open: boolean;
    /**
     * The list the palette shows.
     */
    page: PalettePage;
    setPage: (page: PalettePage) => void;
    setOpen: (open: boolean) => void;
    /**
     * Open the palette on one of its lists, or close it when it already shows that list.
     */
    toggle: (page?: PalettePage) => void;
};

const CommandModalContext = createContext<CommandModalContextType>({
    open: false,
    page: 'root',
    setPage: () => {},
    setOpen: () => {},
    toggle: () => {},
});

/**
 * Holds the state of the command palette and its shortcuts: Ctrl+K opens its commands, Ctrl+O the projects to open.
 * Ctrl+O also works while typing, like the shortcut creating a project, or the browser would ask for a file to open.
 */
const CommandModalProvider = ({ children }: { children: React.ReactNode }) => {
    const [open, setOpen] = useState(false);
    const [page, setPage] = useState<PalettePage>('root');

    const toggle = useCallback(
        (target: PalettePage = 'root') => {
            const isShowingTarget = open && page === target;

            setPage(target);
            setOpen(!isShowingTarget);
        },
        [open, page],
    );

    // The list stays the same while the palette fades out on closing.
    const openOnRoot = useCallback((isOpen: boolean) => {
        if (isOpen) {
            setPage('root');
        }

        setOpen(isOpen);
    }, []);

    useHotkeys('mod+k', () => toggle('root'), { preventDefault: true }, [
        toggle,
    ]);
    useHotkeys(
        'mod+o',
        () => toggle('projects'),
        { preventDefault: true, enableOnFormTags: true },
        [toggle],
    );

    const value = useMemo(
        () => ({ open, page, setPage, setOpen: openOnRoot, toggle }),
        [open, page, openOnRoot, toggle],
    );

    return <CommandModalContext value={value}>{children}</CommandModalContext>;
};

const useCommandModal = () => {
    const context = useContext(CommandModalContext);

    if (!context) {
        throw new Error(
            'useCommandModal must be used within a CommandModalProvider',
        );
    }

    return context;
};

export { CommandModalProvider, useCommandModal };
