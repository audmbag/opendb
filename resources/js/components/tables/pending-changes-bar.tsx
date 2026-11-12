import { useLaravelReactI18n } from 'laravel-react-i18n';
import { CircleAlertIcon, DatabaseZapIcon, Undo2Icon } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import Alert from '@/components/alert';
import Button from '@/components/form/button';
import Modal from '@/components/modals/modal';
import ModalFooter from '@/components/modals/modal-footer';
import { trans, transChoice } from '@/lib/i18n';
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
                    ? trans('tables.changes.new_row')
                    : trans('tables.changes.row', { number: row.number ?? '' });

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

/**
 * « 1 ligne modifiée », « 3 lignes ajoutées », in the language of the interface.
 */
const rows = (count: number, action: 'updated' | 'inserted' | 'deleted') =>
    transChoice(`tables.changes.rows_${action}`, count);

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
    const { t, tChoice } = useLaravelReactI18n();
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
            toast.success(t('tables.changes.saved'), {
                description: [
                    applied.updated && rows(applied.updated, 'updated'),
                    applied.inserted && rows(applied.inserted, 'inserted'),
                    applied.deleted && rows(applied.deleted, 'deleted'),
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
                    ? t('tables.changes.nothing_saved')
                    : t('tables.changes.some_invalid'),
                {
                    description:
                        Object.keys(onTargets).length > 0
                            ? t('tables.changes.errors_below')
                            : error.first(),
                },
            );
        } finally {
            setProcessing(null);
        }
    };

    const summary = [
        Object.keys(updates).length &&
            tChoice('tables.changes.updates', Object.keys(updates).length),
        inserts.length && tChoice('tables.changes.inserts', inserts.length),
        Object.keys(deletes).length &&
            tChoice('tables.changes.deletes', Object.keys(deletes).length),
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
                            ? t('tables.changes.not_applied')
                            : tChoice(
                                  'tables.changes.errors_to_fix',
                                  errorCount,
                              )
                    }
                    message={
                        failure ??
                        (elsewhere > 0
                            ? tChoice(
                                  'tables.changes.errors_elsewhere',
                                  elsewhere,
                              )
                            : t('tables.changes.click_error'))
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
                aria-label={t('tables.changes.pending')}
                className="flex flex-wrap items-center gap-3 border-t bg-warning/10 px-3 py-2 dark:bg-warning/15"
            >
                <p className="min-w-0 flex-1 text-sm">
                    <span className="font-medium">{summary}</span>
                    <span className="text-muted-foreground">
                        {' '}
                        {t('tables.changes.waiting')}
                    </span>
                    {errorCount > 0 && (
                        <span className="ml-3 inline-flex items-center gap-1 text-destructive">
                            <CircleAlertIcon className="size-3.5" />
                            {tChoice('tables.changes.errors_count', errorCount)}
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
                    {t('tables.changes.discard_all')}
                </Button>
                <Button
                    size="sm"
                    onClick={() => send('preview')}
                    disabled={processing !== null}
                    processing={processing === 'preview'}
                >
                    {t('tables.changes.review_and_apply')}
                </Button>
            </div>

            <Modal
                open={statements !== null}
                showHeader
                scrollable
                className="h-auto sm:max-w-2xl"
                onOpenChange={(open) =>
                    !open && processing === null && setStatements(null)
                }
                icon={DatabaseZapIcon}
                title={tChoice('tables.changes.apply_to', count, { table })}
                description={t('tables.changes.single_transaction')}
            >
                <div className="flex flex-col">
                    <div className="p-4">
                        <div className="max-h-[50vh] overflow-auto rounded-md border">
                            <SqlCodeBlock
                                code={(statements ?? []).join('\n')}
                                className="border-0 [&_pre]:break-all [&_pre]:whitespace-pre-wrap"
                            />
                        </div>
                    </div>
                    <ModalFooter>
                        <Button
                            variant="outline"
                            onClick={() => setStatements(null)}
                            disabled={processing !== null}
                        >
                            {t('tables.changes.back_to_grid')}
                        </Button>
                        <Button
                            onClick={() => send('apply')}
                            disabled={processing !== null}
                            processing={processing === 'apply'}
                        >
                            {tChoice('tables.changes.apply', count)}
                        </Button>
                    </ModalFooter>
                </div>
            </Modal>
        </>
    );
}
