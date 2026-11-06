import {
    FileCodeIcon,
    FilePlusIcon,
    PencilIcon,
    SearchIcon,
    Trash2Icon,
} from 'lucide-react';
import { useDeferredValue, useRef, useState } from 'react';
import { formatBytes } from '@/components/ai/artifacts/format';
import { EmptyState } from '@/components/empty-state';
import Button from '@/components/form/button';
import {
    ContextMenu,
    ContextMenuContent,
    ContextMenuItem,
    ContextMenuSeparator,
    ContextMenuTrigger,
} from '@/components/ui/context-menu';
import {
    Editable,
    EditableArea,
    EditableInput,
    EditablePreview,
} from '@/components/ui/editable';
import { cn } from '@/lib/utils';
import type { SqlFileSummary } from './types';

type Props = {
    files: SqlFileSummary[];
    /**
     * The file of the tab shown, and the files opened in a tab.
     */
    current: string | null;
    openFiles: string[];
    /**
     * The file whose name is being edited, from the panel or the menu of its tab.
     */
    renaming: string | null;
    onRenamingChange: (name: string | null) => void;
    onOpen: (name: string) => void;
    onCreate: () => void;
    onRename: (name: string, newName: string) => void;
    onDelete: (name: string) => void;
    creating: boolean;
};

/**
 * The .sql files of the project: each one opens in a tab, which saves it as the user types.
 */
export default function FilesPanel({
    files,
    current,
    openFiles,
    renaming,
    onRenamingChange,
    onOpen,
    onCreate,
    onRename,
    onDelete,
    creating,
}: Props) {
    const [search, setSearch] = useState('');
    const renameAfterClose = useRef<string | null>(null);
    const deferredSearch = useDeferredValue(search.trim().toLowerCase());
    const visible =
        deferredSearch === ''
            ? files
            : files.filter((file) =>
                  file.name.toLowerCase().includes(deferredSearch),
              );

    return (
        <nav
            aria-label="Fichiers SQL"
            className="flex h-full min-h-0 min-w-0 flex-col overflow-hidden"
        >
            <div className="flex min-w-0 items-center gap-0.5 px-2 pt-2 pb-1.5">
                <h2 className="flex min-w-0 flex-1 items-baseline gap-2 truncate pl-1 text-sm font-medium">
                    Fichiers
                    <span className="text-xs font-normal text-muted-foreground tabular-nums">
                        {files.length}
                    </span>
                </h2>
                <Button
                    variant="ghost"
                    size="icon-sm"
                    onClick={onCreate}
                    processing={creating}
                    aria-label="Nouveau fichier SQL"
                    tooltip="Nouveau fichier SQL"
                >
                    <FilePlusIcon />
                </Button>
            </div>

            {files.length > 0 && (
                <label className="relative mx-2 mb-2 flex items-center">
                    <span className="sr-only">Rechercher un fichier</span>
                    <SearchIcon className="pointer-events-none absolute left-2.5 size-4 text-muted-foreground" />
                    <input
                        type="search"
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        onKeyDown={(event) => {
                            if (event.key === 'Enter' && visible[0]) {
                                onOpen(visible[0].name);
                            } else if (event.key === 'Escape') {
                                setSearch('');
                            }
                        }}
                        placeholder="Rechercher"
                        className="h-8 w-full rounded-md border border-input bg-transparent pr-2 pl-8 text-sm outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 dark:bg-input/30"
                    />
                </label>
            )}

            <div className="min-h-0 flex-1 overflow-y-auto px-2 pb-2">
                {files.length === 0 ? (
                    <EmptyState
                        icon={FileCodeIcon}
                        title="Aucun fichier"
                        description="Chaque onglet est un fichier .sql du dossier .opendb/sql du projet."
                        actions={[
                            {
                                label: 'Nouveau fichier',
                                icon: FilePlusIcon,
                                variant: 'outline',
                                onClick: onCreate,
                            },
                        ]}
                        className="py-6"
                    />
                ) : visible.length === 0 ? (
                    <p className="px-2 py-6 text-center text-sm text-muted-foreground">
                        Aucun fichier ne correspond.
                    </p>
                ) : (
                    <ul className="flex flex-col gap-px">
                        {visible.map((file) => {
                            const selected = file.name === current;
                            const opened = openFiles.includes(file.name);

                            return (
                                <li key={file.name}>
                                    {renaming === file.name ? (
                                        <Editable
                                            defaultValue={file.name}
                                            editing
                                            onSubmit={(value) => {
                                                onRenamingChange(null);

                                                const trimmed = value.trim();

                                                if (
                                                    trimmed !== '' &&
                                                    trimmed !== file.name
                                                ) {
                                                    onRename(
                                                        file.name,
                                                        trimmed.endsWith('.sql')
                                                            ? trimmed
                                                            : `${trimmed}.sql`,
                                                    );
                                                }
                                            }}
                                            onCancel={() =>
                                                onRenamingChange(null)
                                            }
                                            placeholder="nom.sql"
                                            className="h-8 px-1 text-sm"
                                        >
                                            <EditableArea>
                                                <EditablePreview />
                                                <EditableInput
                                                    className="px-1"
                                                    autoComplete="off"
                                                    aria-label={`Nouveau nom de ${file.name}`}
                                                />
                                            </EditableArea>
                                        </Editable>
                                    ) : (
                                        <ContextMenu modal={false}>
                                            <ContextMenuTrigger asChild>
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        onOpen(file.name)
                                                    }
                                                    onDoubleClick={() =>
                                                        onRenamingChange(
                                                            file.name,
                                                        )
                                                    }
                                                    aria-current={
                                                        selected
                                                            ? 'page'
                                                            : undefined
                                                    }
                                                    title={file.name}
                                                    className={cn(
                                                        'flex h-8 w-full min-w-0 items-center gap-2 rounded-md px-2 text-left text-sm transition-colors hover:bg-muted',
                                                        selected
                                                            ? 'bg-muted font-medium text-foreground'
                                                            : 'text-muted-foreground hover:text-foreground',
                                                    )}
                                                >
                                                    <FileCodeIcon
                                                        className={cn(
                                                            'size-4 shrink-0',
                                                            selected &&
                                                                'text-sidebar-primary',
                                                        )}
                                                    />
                                                    <span className="min-w-0 flex-1 truncate">
                                                        {file.name}
                                                    </span>
                                                    {opened && !selected && (
                                                        <span
                                                            className="size-1.5 shrink-0 rounded-full bg-muted-foreground/60"
                                                            aria-label="Ouvert dans un onglet"
                                                        />
                                                    )}
                                                    <span className="shrink-0 text-xs text-muted-foreground tabular-nums">
                                                        {formatBytes(file.size)}
                                                    </span>
                                                </button>
                                            </ContextMenuTrigger>
                                            <ContextMenuContent
                                                className="w-52"
                                                onCloseAutoFocus={(event) => {
                                                    // The field replaces the file: the focus must stay in it.
                                                    if (
                                                        renameAfterClose.current
                                                    ) {
                                                        event.preventDefault();
                                                        onRenamingChange(
                                                            renameAfterClose.current,
                                                        );
                                                        renameAfterClose.current =
                                                            null;
                                                    }
                                                }}
                                            >
                                                <ContextMenuItem
                                                    onSelect={() =>
                                                        onOpen(file.name)
                                                    }
                                                >
                                                    <FileCodeIcon />
                                                    Ouvrir
                                                </ContextMenuItem>
                                                <ContextMenuItem
                                                    onSelect={() => {
                                                        renameAfterClose.current =
                                                            file.name;
                                                    }}
                                                >
                                                    <PencilIcon />
                                                    Renommer
                                                </ContextMenuItem>
                                                <ContextMenuSeparator />
                                                <ContextMenuItem
                                                    variant="destructive"
                                                    onSelect={() =>
                                                        onDelete(file.name)
                                                    }
                                                >
                                                    <Trash2Icon />
                                                    Supprimer
                                                </ContextMenuItem>
                                            </ContextMenuContent>
                                        </ContextMenu>
                                    )}
                                </li>
                            );
                        })}
                    </ul>
                )}
            </div>
        </nav>
    );
}
