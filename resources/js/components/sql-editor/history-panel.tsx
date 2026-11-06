import {
    CircleCheckIcon,
    CircleXIcon,
    ClipboardCopyIcon,
    HistoryIcon,
    SearchIcon,
    Trash2Icon,
} from 'lucide-react';
import { useDeferredValue, useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import { formatRelativeTime } from '@/components/ai/artifacts/record/format';
import AlertDialog from '@/components/alert-dialog';
import { EmptyState } from '@/components/empty-state';
import Button from '@/components/form/button';
import type { TableRequestError } from '@/components/tables/api';
import { requestTableData } from '@/components/tables/api';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';
import QueryHistoryController from '@/wayfinder/actions/App/Http/Controllers/SqlEditor/QueryHistoryController';
import SqlSnippet from './sql-snippet';

export type HistoryEntry = {
    id: string;
    file: string | null;
    sql: string;
    succeeded: boolean;
    statements: number;
    rows: number | null;
    duration: number;
    error: string | null;
    createdAt: string | null;
};

type Props = {
    projectId: string;
    /**
     * The history is read only while it is shown, then again after each run.
     */
    active: boolean;
    /**
     * Changes after each run of the editor.
     */
    version: number;
    onInsert: (sql: string) => void;
};

/**
 * The scripts run in the editor of the project, kept by the server: a click inserts one in the shown tab.
 */
export default function HistoryPanel({
    projectId,
    active,
    version,
    onInsert,
}: Props) {
    const [entries, setEntries] = useState<HistoryEntry[] | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [search, setSearch] = useState('');
    const deferredSearch = useDeferredValue(search.trim());
    const loaded = useRef<string | null>(null);

    useEffect(() => {
        const key = `${version}:${deferredSearch}`;

        if (!active || loaded.current === key) {
            return;
        }

        const controller = new AbortController();

        requestTableData<HistoryEntry[]>(
            'get',
            QueryHistoryController.index.url(
                { project: projectId },
                { query: deferredSearch ? { search: deferredSearch } : {} },
            ),
            { signal: controller.signal },
        )
            .then((history) => {
                loaded.current = key;
                setEntries(history);
                setError(null);
            })
            .catch((exception: TableRequestError) => {
                if (!exception.cancelled) {
                    setError(exception.first());
                }
            });

        return () => controller.abort();
    }, [active, version, deferredSearch, projectId]);

    const clear = async () => {
        const confirmed = await AlertDialog.call({
            title: "Vider l'historique ?",
            description:
                "Les requêtes exécutées dans l'éditeur de ce projet seront oubliées. Les fichiers .sql ne changent pas.",
            icon: Trash2Icon,
            variant: 'destructive',
            confirmText: "Vider l'historique",
            cancelText: 'Annuler',
        });

        if (!confirmed) {
            return;
        }

        try {
            await requestTableData(
                'delete',
                QueryHistoryController.destroy.url({ project: projectId }),
            );
            setEntries([]);
        } catch (exception) {
            toast.error("L'historique n'a pas été vidé", {
                description: (exception as TableRequestError).first(),
            });
        }
    };

    return (
        <nav
            aria-label="Historique des requêtes"
            className="flex h-full min-h-0 min-w-0 flex-col overflow-hidden"
        >
            <div className="flex min-w-0 items-center gap-0.5 px-2 pt-2 pb-1.5">
                <h2 className="min-w-0 flex-1 truncate pl-1 text-sm font-medium">
                    Historique
                </h2>
                <Button
                    variant="ghost"
                    size="icon-sm"
                    onClick={() => void clear()}
                    disabled={!entries?.length}
                    aria-label="Vider l'historique"
                    tooltip="Vider l'historique"
                >
                    <Trash2Icon />
                </Button>
            </div>

            <label className="relative mx-2 mb-2 flex items-center">
                <span className="sr-only">Rechercher dans l'historique</span>
                <SearchIcon className="pointer-events-none absolute left-2.5 size-4 text-muted-foreground" />
                <input
                    type="search"
                    value={search}
                    onChange={(event) => setSearch(event.target.value)}
                    placeholder="Rechercher"
                    className="h-8 w-full rounded-md border border-input bg-transparent pr-2 pl-8 text-sm outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 dark:bg-input/30"
                />
            </label>

            <div className="min-h-0 flex-1 overflow-y-auto px-2 pb-2">
                {error ? (
                    <p className="px-2 py-6 text-center text-sm text-destructive">
                        {error}
                    </p>
                ) : entries === null ? (
                    <div className="flex flex-col gap-2 px-1" aria-busy="true">
                        {[0, 1, 2].map((index) => (
                            <Skeleton key={index} className="h-14 w-full" />
                        ))}
                    </div>
                ) : entries.length === 0 ? (
                    <EmptyState
                        icon={HistoryIcon}
                        title={search ? 'Aucun résultat' : 'Aucune requête'}
                        description={
                            search
                                ? 'Aucune requête exécutée ne contient ce texte.'
                                : "Les requêtes exécutées dans l'éditeur apparaissent ici."
                        }
                        className="py-6"
                    />
                ) : (
                    <ul className="flex flex-col gap-1">
                        {entries.map((entry) => (
                            <HistoryItem
                                key={entry.id}
                                entry={entry}
                                onInsert={onInsert}
                            />
                        ))}
                    </ul>
                )}
            </div>
        </nav>
    );
}

function HistoryItem({
    entry,
    onInsert,
}: {
    entry: HistoryEntry;
    onInsert: (sql: string) => void;
}) {
    const StatusIcon = entry.succeeded ? CircleCheckIcon : CircleXIcon;
    const when = entry.createdAt ? formatRelativeTime(entry.createdAt) : null;
    const details = [
        `${entry.duration} ms`,
        entry.statements > 1 && `${entry.statements} instructions`,
        entry.rows !== null &&
            `${entry.rows} ligne${entry.rows > 1 ? 's' : ''}`,
    ].filter(Boolean);

    return (
        <li className="group/entry relative">
            <button
                type="button"
                onClick={() => onInsert(entry.sql)}
                title="Insérer dans l'onglet affiché"
                className="flex w-full min-w-0 flex-col gap-1.5 rounded-md border border-transparent px-2 py-2 text-left transition-colors hover:border-border hover:bg-background focus-visible:border-ring focus-visible:outline-none"
            >
                <span className="flex min-w-0 items-center gap-1.5 pr-7 text-[11px] text-muted-foreground">
                    <StatusIcon
                        className={cn(
                            'size-3.5 shrink-0',
                            entry.succeeded
                                ? 'text-success'
                                : 'text-destructive',
                        )}
                        aria-label={entry.succeeded ? 'Réussie' : 'Échouée'}
                    />
                    <span className="min-w-0 truncate font-medium text-foreground/80">
                        {entry.file ?? 'Sans fichier'}
                    </span>
                    {when && (
                        <span className="ml-auto shrink-0 tabular-nums">
                            {when}
                        </span>
                    )}
                </span>
                <SqlSnippet sql={entry.sql} />
                {entry.error ? (
                    <span className="line-clamp-2 text-[11px] break-words text-destructive">
                        {entry.error}
                    </span>
                ) : (
                    <span className="text-[11px] text-muted-foreground tabular-nums">
                        {details.join(' · ')}
                    </span>
                )}
            </button>
            <Button
                variant="ghost"
                size="icon-sm"
                className="absolute top-1 right-1 size-6 opacity-0 group-hover/entry:opacity-100 focus-visible:opacity-100"
                aria-label="Copier la requête"
                tooltip="Copier"
                onClick={() =>
                    navigator.clipboard
                        .writeText(entry.sql)
                        .then(() =>
                            toast.success('Requête copiée', { duration: 1500 }),
                        )
                }
            >
                <ClipboardCopyIcon />
            </Button>
        </li>
    );
}
