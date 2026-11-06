import {
    FileCodeIcon,
    SquareTerminalIcon,
    Table2Icon,
    XIcon,
} from 'lucide-react';
import { useEffect, useRef } from 'react';
import type { ReactNode } from 'react';
import { useStore } from 'zustand';
import {
    ContextMenu,
    ContextMenuContent,
    ContextMenuItem,
    ContextMenuTrigger,
} from '@/components/ui/context-menu';
import { cn } from '@/lib/utils';
import type { TableChangesStore } from '@/store/use-table-changes-store';
import { pendingChangesCount } from '@/store/use-table-changes-store';

export type TabItem = {
    id: string;
    kind: 'table' | 'console' | 'file';
    title: string;
    /**
     * The changes of the table waiting to be applied, shown as a dot on its tab.
     */
    changes: TableChangesStore | null;
    /**
     * A state shown after the title, like a file being saved.
     */
    indicator?: ReactNode;
};

const ICONS = {
    table: Table2Icon,
    console: SquareTerminalIcon,
    file: FileCodeIcon,
};

type Props = {
    tabs: TabItem[];
    activeId: string | null;
    onSelect: (id: string) => void;
    onClose: (id: string) => void;
    onCloseOthers: (id: string) => void;
    onCloseAll: () => void;
    onRename?: (id: string) => void;
    label?: string;
};

function PendingDot({ store }: { store: TableChangesStore }) {
    const count = useStore(store, pendingChangesCount);

    return count > 0 ? (
        <span
            className="size-1.5 shrink-0 rounded-full bg-warning"
            title={`${count} modification${count > 1 ? 's' : ''} en attente`}
        />
    ) : null;
}

/**
 * The tables and consoles opened at once, each tab keeping its state while another one is shown.
 * A middle click closes a tab, as in a browser.
 */
export default function TableTabs({
    tabs,
    activeId,
    onSelect,
    onClose,
    onCloseOthers,
    onCloseAll,
    onRename,
    label = 'Tables ouvertes',
}: Props) {
    const listRef = useRef<HTMLDivElement>(null);
    /**
     * The tab to rename once its menu is closed: the menu gives the focus back to the tab when it closes, which
     * would take it away from the field the rename opens.
     */
    const renameAfterClose = useRef<string | null>(null);

    useEffect(() => {
        listRef.current
            ?.querySelector<HTMLElement>('[aria-selected="true"]')
            ?.scrollIntoView({ block: 'nearest', inline: 'nearest' });
    }, [activeId]);

    if (tabs.length === 0) {
        return null;
    }

    return (
        <div
            ref={listRef}
            // The scrollbar is hidden so the selected tab joins the panel under it: the wheel scrolls the tabs instead.
            onWheel={(event) => {
                if (event.deltaY !== 0 && event.deltaX === 0) {
                    event.currentTarget.scrollLeft += event.deltaY;
                }
            }}
            role="tablist"
            aria-label={label}
            className="relative z-10 -mb-px flex shrink-0 [scrollbar-width:none] items-end gap-0.5 overflow-x-auto"
        >
            {tabs.map((tab) => {
                const selected = tab.id === activeId;
                const Icon = ICONS[tab.kind];

                return (
                    <ContextMenu key={tab.id} modal={false}>
                        <ContextMenuTrigger asChild>
                            <div
                                role="tab"
                                aria-selected={selected}
                                tabIndex={selected ? 0 : -1}
                                onClick={() => onSelect(tab.id)}
                                onAuxClick={(event) => {
                                    if (event.button === 1) {
                                        event.preventDefault();
                                        onClose(tab.id);
                                    }
                                }}
                                onKeyDown={(event) => {
                                    if (
                                        event.key === 'Enter' ||
                                        event.key === ' '
                                    ) {
                                        event.preventDefault();
                                        onSelect(tab.id);
                                    } else if (event.key === 'Delete') {
                                        onClose(tab.id);
                                    } else if (
                                        event.key === 'ArrowRight' ||
                                        event.key === 'ArrowLeft'
                                    ) {
                                        const index = tabs.findIndex(
                                            (item) => item.id === tab.id,
                                        );
                                        const next =
                                            tabs[
                                                (index +
                                                    (event.key === 'ArrowRight'
                                                        ? 1
                                                        : -1) +
                                                    tabs.length) %
                                                    tabs.length
                                            ];

                                        onSelect(next.id);
                                    }
                                }}
                                title={tab.title}
                                className={cn(
                                    'group/tab flex h-8 max-w-56 min-w-0 shrink-0 cursor-pointer items-center gap-1.5 rounded-t-md border border-b-0 pr-1 pl-2.5 text-sm transition-colors outline-none select-none focus-visible:ring-2 focus-visible:ring-ring/50',
                                    selected
                                        ? 'bg-background font-medium text-foreground'
                                        : 'border-transparent text-muted-foreground hover:bg-muted/60 hover:text-foreground',
                                )}
                            >
                                <Icon
                                    className={cn(
                                        'size-3.5 shrink-0',
                                        selected
                                            ? 'text-sidebar-primary'
                                            : 'text-muted-foreground',
                                    )}
                                />
                                <span className="min-w-0 truncate">
                                    {tab.title}
                                </span>
                                {tab.changes && (
                                    <PendingDot store={tab.changes} />
                                )}
                                {tab.indicator}
                                <button
                                    type="button"
                                    tabIndex={-1}
                                    onClick={(event) => {
                                        event.stopPropagation();
                                        onClose(tab.id);
                                    }}
                                    aria-label={`Fermer ${tab.title}`}
                                    className={cn(
                                        'flex size-5 shrink-0 items-center justify-center rounded-sm text-muted-foreground transition-opacity hover:bg-muted hover:text-foreground',
                                        !selected &&
                                            'opacity-0 group-hover/tab:opacity-100 focus-visible:opacity-100',
                                    )}
                                >
                                    <XIcon className="size-3.5" />
                                </button>
                            </div>
                        </ContextMenuTrigger>
                        <ContextMenuContent
                            className="w-52"
                            onCloseAutoFocus={(event) => {
                                if (onRename && renameAfterClose.current) {
                                    event.preventDefault();
                                    onRename(renameAfterClose.current);
                                    renameAfterClose.current = null;
                                }
                            }}
                        >
                            {onRename && (
                                <ContextMenuItem
                                    onSelect={() => {
                                        renameAfterClose.current = tab.id;
                                    }}
                                >
                                    Renommer
                                </ContextMenuItem>
                            )}
                            <ContextMenuItem onSelect={() => onClose(tab.id)}>
                                Fermer
                            </ContextMenuItem>
                            <ContextMenuItem
                                onSelect={() => onCloseOthers(tab.id)}
                                disabled={tabs.length === 1}
                            >
                                Fermer les autres onglets
                            </ContextMenuItem>
                            <ContextMenuItem onSelect={onCloseAll}>
                                Fermer tous les onglets
                            </ContextMenuItem>
                        </ContextMenuContent>
                    </ContextMenu>
                );
            })}
        </div>
    );
}
