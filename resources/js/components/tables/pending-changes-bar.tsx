import { CircleAlertIcon, Undo2Icon } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import Alert from '@/components/alert';
import Button from '@/components/form/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    collectChanges,
    errorsOnTargets,
    pendingChangesCount,
    useTableChangesApi,
    useTableChangesStore,
} from '@/store/use-table-changes-store';
import { useTableGridStore } from '@/store/use-table-grid-store';
import TableChangesController from '@/wayfinder/actions/App/Http/Controllers/Tables/TableChangesController';
import type { TableRequestError } from './api';
import { requestTableData } from './api';
import type { GridRow } from './data-grid/data-grid';
import SqlCodeBlock from './sql-code-block';
import type { TableColumn } from './types';

type Props = {
    projectId: string;
    table: string;
    /**
     * The rows of the grid, to say where each error is and select its cell.
     */
    rows: GridRow[];
    columns: TableColumn[];
    onApplied: () => void;
};

type LocatedError = {
    key: string;
    row: number;
    column: number | null;
    where: string;
    message: string;
};

/**
 * The errors of the rows shown in the grid, in the order of the grid.
 */
function locateErrors(rows: GridRow[], columns: TableColumn[]): LocatedError[] {
    return rows.flatMap((row, rowIndex) =>
        Object.entries(row.errors).map(([column, message]) => {
            const columnIndex = columns.findIndex(
                (item) => item.name === column,
            );
            const line =
                row.state === 'inserted'
                    ? 'Nouvelle ligne'
                    : `Ligne ${row.number}`;

            return {
                key: `${row.id}:${column}`,
                row: rowIndex,
                column: columnIndex >= 0 ? columnIndex : null,
                where: column ? `${line} · ${column}` : line,
                message,
            };
        }),
    );
}

const plural = (count: number, word: string) =>
    `${count} ${word}${count > 1 ? 's' : ''}`;

/**
 * « 1 ligne modifiée », « 3 lignes ajoutées ».
 */
const rows = (count: number, participle: string) =>
    `${count} ligne${count > 1 ? 's' : ''} ${participle}${count > 1 ? 's' : ''}`;

/**
 * The changes waiting to be applied, always in sight. They are applied only after the user has read the statements
 * they run, and together: if the database refuses one, none of them is kept and the faulty cells are pointed at.
 */
export default function PendingChangesBar({
    projectId,
    table,
    rows: gridRows,
    columns,
    onApplied,
}: Props) {
    const updates = useTableChangesStore((state) => state.updates);
    const inserts = useTableChangesStore((state) => state.inserts);
    const deletes = useTableChangesStore((state) => state.deletes);
    const errors = useTableChangesStore((state) => state.errors);
    const setErrors = useTableChangesStore((state) => state.setErrors);
    const discard = useTableChangesStore((state) => state.discard);
    const changesApi = useTableChangesApi();
    const selectCell = useTableGridStore((state) => state.select);

    /**
     * The refusal of the server when it is about none of the changes in particular, like a lost connection.
     */
    const [failure, setFailure] = useState<string | null>(null);

    const [statements, setStatements] = useState<string[] | null>(null);
    const [processing, setProcessing] = useState<'preview' | 'apply' | null>(
        null,
    );

    const count = pendingChangesCount({ updates, inserts, deletes });
    const errorCount = Object.keys(errors).length;
    const located = locateErrors(gridRows, columns);
    const elsewhere = errorCount - located.length;
    const routeArgs = { project: projectId, table: encodeURIComponent(table) };

    if (count === 0) {
        return null;
    }

    const send = async (mode: 'preview' | 'apply') => {
        const { changes, targets } = collectChanges(changesApi.getState());

        setProcessing(mode);
        setFailure(null);

        try {
            if (mode === 'preview') {
                const preview = await requestTableData<{
                    statements: string[];
                }>('post', TableChangesController.preview.url(routeArgs), {
                    data: { changes },
                });

                setErrors({});
                setStatements(preview.statements);

                return;
            }

            const applied = await requestTableData<{
                inserted: number;
                updated: number;
                deleted: number;
            }>('post', TableChangesController.store.url(routeArgs), {
                data: { changes },
            });

            setStatements(null);
            discard();
            onApplied();
            toast.success('Modifications enregistrées', {
                description: [
                    applied.updated && rows(applied.updated, 'modifiée'),
                    applied.inserted && rows(applied.inserted, 'ajoutée'),
                    applied.deleted && rows(applied.deleted, 'supprimée'),
                ]
                    .filter(Boolean)
                    .join(', '),
            });
        } catch (exception) {
            const error = exception as TableRequestError;
            const onTargets = errorsOnTargets(error.errors, targets);

            setStatements(null);
            setErrors(onTargets);
            setFailure(
                Object.keys(onTargets).length > 0 ? null : error.first(),
            );
            toast.error(
                mode === 'apply'
                    ? "Aucune modification n'a été enregistrée"
                    : 'Certaines modifications ne sont pas valides',
                {
                    description:
                        Object.keys(onTargets).length > 0
                            ? 'Le détail des erreurs est affiché sous la grille.'
                            : error.first(),
                },
            );
        } finally {
            setProcessing(null);
        }
    };

    const summary = [
        Object.keys(updates).length &&
            plural(Object.keys(updates).length, 'modification'),
        inserts.length && plural(inserts.length, 'ajout'),
        Object.keys(deletes).length &&
            plural(Object.keys(deletes).length, 'suppression'),
    ]
        .filter(Boolean)
        .join(', ');

    return (
        <>
            {(errorCount > 0 || failure) && (
                <Alert
                    variant="destructive"
                    title={
                        failure
                            ? "Les modifications n'ont pas été appliquées"
                            : `${plural(errorCount, 'erreur')} à corriger avant d'appliquer`
                    }
                    message={
                        failure ??
                        (elsewhere > 0
                            ? `${elsewhere === 1 ? '1 autre erreur porte sur une ligne' : `${elsewhere} autres erreurs portent sur des lignes`} d’une autre page.`
                            : 'Cliquez sur une erreur pour sélectionner sa cellule.')
                    }
                    className="m-2 w-auto"
                >
                    {located.length > 0 && (
                        <ul className="max-h-32 space-y-0.5 overflow-y-auto text-sm">
                            {located.map((item) => (
                                <li key={item.key}>
                                    <button
                                        type="button"
                                        onClick={() =>
                                            selectCell({
                                                row: item.row,
                                                column: item.column ?? 0,
                                            })
                                        }
                                        className="w-full rounded-sm text-left outline-none hover:underline focus-visible:ring-2 focus-visible:ring-ring/50"
                                    >
                                        <span className="font-mono text-xs opacity-80">
                                            {item.where}
                                        </span>{' '}
                                        <span className="break-words">
                                            {item.message}
                                        </span>
                                    </button>
                                </li>
                            ))}
                        </ul>
                    )}
                </Alert>
            )}
            <div
                role="region"
                aria-label="Modifications en attente"
                className="flex flex-wrap items-center gap-3 border-t bg-warning/10 px-3 py-2 dark:bg-warning/15"
            >
                <p className="min-w-0 flex-1 text-sm">
                    <span className="font-medium">{summary}</span>
                    <span className="text-muted-foreground"> en attente</span>
                    {errorCount > 0 && (
                        <span className="ml-3 inline-flex items-center gap-1 text-destructive">
                            <CircleAlertIcon className="size-3.5" />
                            {plural(errorCount, 'erreur')} à corriger
                        </span>
                    )}
                </p>
                <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => {
                        setFailure(null);
                        discard();
                    }}
                    disabled={processing !== null}
                >
                    <Undo2Icon />
                    Tout annuler
                </Button>
                <Button
                    size="sm"
                    onClick={() => send('preview')}
                    disabled={processing !== null}
                    processing={processing === 'preview'}
                >
                    Vérifier et appliquer
                </Button>
            </div>

            <Dialog
                open={statements !== null}
                onOpenChange={(open) =>
                    !open && processing === null && setStatements(null)
                }
            >
                <DialogContent className="sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>
                            Appliquer {plural(count, 'changement')} à {table}
                        </DialogTitle>
                        <DialogDescription>
                            Ces instructions s'exécutent dans une seule
                            transaction. Si la base en refuse une, aucune n'est
                            conservée.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="max-h-[50vh] overflow-auto rounded-md border">
                        <SqlCodeBlock
                            code={(statements ?? []).join('\n')}
                            className="border-0 [&_pre]:break-all [&_pre]:whitespace-pre-wrap"
                        />
                    </div>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setStatements(null)}
                            disabled={processing !== null}
                        >
                            Revenir à la grille
                        </Button>
                        <Button
                            onClick={() => send('apply')}
                            disabled={processing !== null}
                            processing={processing === 'apply'}
                        >
                            Appliquer {plural(count, 'changement')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
