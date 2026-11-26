import { useLaravelReactI18n } from 'laravel-react-i18n';
import {
    ArrowRightIcon,
    GripVerticalIcon,
    KeyRoundIcon,
    PencilRulerIcon,
    WorkflowIcon,
} from 'lucide-react';
import { lazy, Suspense, useEffect, useState } from 'react';
import Button from '@/components/form/button';
import { Badge } from '@/components/reui/badge';
import type { SchemaExport } from '@/components/schema-viewer/types';
import SortableList, { useSortableItem } from '@/components/sortable-list';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';
import DatabaseSchemaController from '@/wayfinder/actions/App/Http/Controllers/Chat/DatabaseSchemaController';
import { requestTableData } from './api';
import ColumnMoveDialog from './column-move-dialog';
import type { TableStructure } from './types';
import { reorderColumns, useColumnMove } from './use-column-move';

const SchemaCanvas = lazy(
    () => import('@/components/schema-viewer/schema-canvas'),
);

type Props = {
    projectId: string;
    structure: TableStructure;
    onOpenTable: (table: string) => void;
    /**
     * Opens the designer of the table, left out when the privileges of the user do not let them change it.
     */
    onEditStructure?: () => void;
    /**
     * The engine of the project, to say how it moves a column.
     */
    driver: string;
    /**
     * Called once a column is moved, left out when the columns cannot be moved: without the ALTER privilege, or on
     * PostgreSQL, which keeps its columns in the order they were added.
     */
    onColumnMoved?: () => void;
};

function Section({
    title,
    count,
    children,
}: {
    title: string;
    count: number;
    children: React.ReactNode;
}) {
    return (
        <section className="flex flex-col gap-2">
            <h3 className="flex items-baseline gap-2 text-sm font-medium">
                {title}
                <span className="text-xs font-normal text-muted-foreground tabular-nums">
                    {count}
                </span>
            </h3>
            {children}
        </section>
    );
}

function TableLink({
    table,
    onOpen,
}: {
    table: string;
    onOpen: (table: string) => void;
}) {
    return (
        <button
            type="button"
            onClick={() => onOpen(table)}
            className="font-medium underline-offset-4 hover:text-sidebar-primary hover:underline"
        >
            {table}
        </button>
    );
}

/**
 * The diagram of the table and its neighbours, drawn only when asked for: its layout is the costliest part of the page.
 */
function RelationsDiagram({
    projectId,
    table,
}: {
    projectId: string;
    table: string;
}) {
    const [schema, setSchema] = useState<SchemaExport | null>(null);

    useEffect(() => {
        const controller = new AbortController();

        requestTableData<SchemaExport>(
            'get',
            DatabaseSchemaController.table.url(
                { project: projectId, table: encodeURIComponent(table) },
                { query: { withRelatedTables: true } },
            ),
            {
                signal: controller.signal,
            },
        )
            .then(setSchema)
            .catch(() => null);

        return () => controller.abort();
    }, [projectId, table]);

    return (
        <div className="h-120 overflow-hidden rounded-lg border">
            {schema ? (
                <Suspense
                    fallback={<Skeleton className="size-full rounded-none" />}
                >
                    <SchemaCanvas schema={schema} />
                </Suspense>
            ) : (
                <Skeleton className="size-full rounded-none" />
            )}
        </div>
    );
}

/**
 * A column of the table, dragged by its handle to another place when the table can be changed.
 */
function StructureColumnRow({
    name,
    movable,
    disabled,
    pending,
    settled,
    children,
}: {
    name: string;
    movable: boolean;
    disabled: boolean;
    /**
     * Whether the column was just dropped there and waits for its statements to be confirmed.
     */
    pending: boolean;
    /**
     * Whether the column was just moved there: it lights up once.
     */
    settled: boolean;
    children: React.ReactNode;
}) {
    const { t } = useLaravelReactI18n();
    const { setNodeRef, setHandleRef, handleProps, style, dragging } =
        useSortableItem(name, !movable || disabled);

    return (
        <tr
            ref={setNodeRef}
            style={style}
            className={cn(
                'border-t bg-background transition-[box-shadow,background-color] duration-200',
                dragging &&
                    'bg-muted shadow-[0_8px_24px_-8px_rgb(0_0_0/0.35)] outline-1 outline-border',
                pending && 'bg-muted/60',
                settled && 'sortable-settled',
            )}
        >
            {movable && (
                <td className="w-0 py-2 pr-0 pl-1.5">
                    <button
                        type="button"
                        ref={setHandleRef}
                        {...handleProps}
                        aria-label={t('tables.structure.move_handle', {
                            column: name,
                        })}
                        title={t('tables.structure.move_handle', {
                            column: name,
                        })}
                        disabled={disabled}
                        className={cn(
                            'flex touch-none rounded-sm p-0.5 text-muted-foreground/60 transition-[color,transform] duration-150 outline-none hover:scale-110 hover:text-foreground focus-visible:text-foreground focus-visible:ring-2 focus-visible:ring-ring/50 disabled:opacity-50',
                            dragging
                                ? 'scale-110 cursor-grabbing text-foreground'
                                : 'cursor-grab disabled:cursor-default',
                        )}
                    >
                        <GripVerticalIcon className="size-4" />
                    </button>
                </td>
            )}
            {children}
        </tr>
    );
}

/**
 * The columns of the table with their constraints, its indexes, and the tables it is linked to.
 */
export default function StructureView({
    projectId,
    structure,
    onOpenTable,
    onEditStructure,
    driver,
    onColumnMoved,
}: Props) {
    const { t } = useLaravelReactI18n();
    const [showDiagram, setShowDiagram] = useState(false);
    const columnMove = useColumnMove(
        projectId,
        structure,
        onColumnMoved ?? (() => undefined),
    );
    const canMove = onColumnMoved !== undefined;
    const relations =
        structure.foreignKeys.length + structure.incomingReferences.length;

    // The column being moved shows in its new place while its statements are read and run.
    const pending = columnMove.move;
    const columns = pending
        ? reorderColumns(
              structure.columns.map((column) => column.name),
              pending.column,
              pending.after,
          ).map((name) =>
              structure.columns.find((column) => column.name === name)!,
          )
        : structure.columns;
    const names = columns.map((column) => column.name);

    /**
     * The column dropped on the place of another one goes there: after the column before it, or first.
     */
    const moveColumn = (column: string, over: string) => {
        const target = names.indexOf(over);
        const order = names.filter((name) => name !== column);
        order.splice(target, 0, column);

        void columnMove.request(column, order[target - 1] ?? null);
    };

    return (
        <div className="flex min-h-0 flex-1 flex-col gap-8 overflow-y-auto p-4">
            {onEditStructure && (
                <div className="-mb-4 flex justify-end">
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={onEditStructure}
                    >
                        <PencilRulerIcon />
                        {t('tables.workspace.edit_structure')}
                    </Button>
                </div>
            )}
            <Section
                title={t('ai.record.columns')}
                count={structure.columns.length}
            >
                <div className="overflow-x-auto overflow-y-hidden rounded-lg border">
                    <SortableList
                        items={names}
                        onMove={moveColumn}
                        label={(name) => name}
                    >
                        <table className="w-full text-left text-sm">
                            <thead className="bg-muted/60 text-muted-foreground">
                                <tr>
                                    {canMove && <th className="w-0 p-0" />}
                                    <th className="px-3 py-2 font-medium">
                                        {t('tables.designer.columns.name')}
                                    </th>
                                    <th className="px-3 py-2 font-medium">
                                        {t('tables.designer.columns.type')}
                                    </th>
                                    <th className="px-3 py-2 font-medium">
                                        NULL
                                    </th>
                                    <th className="px-3 py-2 font-medium">
                                        {t('tables.structure.default')}
                                    </th>
                                    <th className="px-3 py-2 font-medium">
                                        {t('tables.structure.constraints')}
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {columns.map((column) => (
                                    <StructureColumnRow
                                        key={column.name}
                                        name={column.name}
                                        movable={canMove}
                                        disabled={pending !== null}
                                        pending={
                                            pending?.column === column.name
                                        }
                                        settled={
                                            columnMove.settled === column.name
                                        }
                                    >
                                        <td className="px-3 py-2 font-medium">
                                            <span className="inline-flex items-center gap-1.5">
                                                {column.primary && (
                                                    <KeyRoundIcon
                                                        className="size-3.5 text-warning"
                                                        aria-label={t(
                                                            'tables.designer.columns.primary_key',
                                                        )}
                                                    />
                                                )}
                                                {column.name}
                                            </span>
                                        </td>
                                        <td className="px-3 py-2 font-mono text-xs text-muted-foreground">
                                            {column.db_type}
                                        </td>
                                        <td className="px-3 py-2 text-muted-foreground">
                                            {column.nullable
                                                ? t('common.yes')
                                                : t('common.no')}
                                        </td>
                                        <td
                                            className="max-w-48 truncate px-3 py-2 font-mono text-xs text-muted-foreground"
                                            title={column.default ?? undefined}
                                        >
                                            {column.default ?? ''}
                                        </td>
                                        <td className="px-3 py-2">
                                            <span className="flex flex-wrap items-center gap-1.5">
                                                {column.auto_increment && (
                                                    <Badge variant="outline">
                                                        {t(
                                                            'tables.structure.auto_increment',
                                                        )}
                                                    </Badge>
                                                )}
                                                {column.unique && (
                                                    <Badge variant="outline">
                                                        {t(
                                                            'tables.records.unique',
                                                        )}
                                                    </Badge>
                                                )}
                                                {column.generated && (
                                                    <Badge variant="outline">
                                                        {t(
                                                            'tables.structure.generated',
                                                        )}
                                                    </Badge>
                                                )}
                                                {column.references && (
                                                    <span className="inline-flex items-center gap-1 text-muted-foreground">
                                                        <ArrowRightIcon className="size-3.5" />
                                                        <span>
                                                            <TableLink
                                                                table={
                                                                    column
                                                                        .references
                                                                        .table
                                                                }
                                                                onOpen={
                                                                    onOpenTable
                                                                }
                                                            />
                                                            .
                                                            {
                                                                column
                                                                    .references
                                                                    .column
                                                            }
                                                        </span>
                                                    </span>
                                                )}
                                            </span>
                                        </td>
                                    </StructureColumnRow>
                                ))}
                            </tbody>
                        </table>
                    </SortableList>
                </div>
            </Section>

            {canMove && (
                <ColumnMoveDialog
                    move={pending}
                    driver={driver}
                    onConfirm={() => void columnMove.confirm()}
                    onCancel={columnMove.cancel}
                />
            )}

            <Section
                title={t('tables.list.sections.indexes')}
                count={
                    structure.indexes.length +
                    (structure.primaryKey.length ? 1 : 0)
                }
            >
                <ul className="flex flex-col divide-y rounded-lg border text-sm">
                    {structure.primaryKey.length > 0 && (
                        <li className="flex flex-wrap items-center gap-x-3 gap-y-1 px-3 py-2">
                            <span className="font-medium">
                                {t('tables.designer.columns.primary_key')}
                            </span>
                            <span className="font-mono text-xs text-muted-foreground">
                                ({structure.primaryKey.join(', ')})
                            </span>
                        </li>
                    )}
                    {structure.indexes.map((index) => (
                        <li
                            key={index.name}
                            className="flex flex-wrap items-center gap-x-3 gap-y-1 px-3 py-2"
                        >
                            <span className="font-medium">{index.name}</span>
                            <span className="font-mono text-xs text-muted-foreground">
                                ({index.columns.join(', ')})
                            </span>
                            {index.unique && (
                                <Badge variant="outline">
                                    {t('tables.records.unique')}
                                </Badge>
                            )}
                        </li>
                    ))}
                    {structure.indexes.length === 0 &&
                        structure.primaryKey.length === 0 && (
                            <li className="px-3 py-4 text-muted-foreground">
                                {t('tables.structure.no_index')}
                            </li>
                        )}
                </ul>
            </Section>

            <Section title={t('ai.record.relations')} count={relations}>
                {relations === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        {t('tables.structure.no_relation')}
                    </p>
                ) : (
                    <ul className="flex flex-col divide-y rounded-lg border text-sm">
                        {structure.foreignKeys.map((foreignKey) => (
                            <li
                                key={`out-${foreignKey.name}-${foreignKey.columns.join()}`}
                                className="flex flex-wrap items-center gap-x-2 gap-y-1 px-3 py-2"
                            >
                                <span className="font-mono text-xs">
                                    {foreignKey.columns.join(', ')}
                                </span>
                                <span className="text-muted-foreground">
                                    {t('tables.structure.references')}
                                </span>
                                <TableLink
                                    table={foreignKey.references_table}
                                    onOpen={onOpenTable}
                                />
                                <span className="font-mono text-xs text-muted-foreground">
                                    ({foreignKey.references_columns.join(', ')})
                                </span>
                                {foreignKey.on_delete &&
                                    foreignKey.on_delete.toLowerCase() !==
                                        'no action' && (
                                        <span className="text-xs text-muted-foreground">
                                            {t('tables.structure.on_delete')}{' '}
                                            {foreignKey.on_delete.toLowerCase()}
                                        </span>
                                    )}
                            </li>
                        ))}
                        {structure.incomingReferences.map((reference) => (
                            <li
                                key={`in-${reference.table}-${reference.columns.join()}`}
                                className="flex flex-wrap items-center gap-x-2 gap-y-1 px-3 py-2"
                            >
                                <TableLink
                                    table={reference.table}
                                    onOpen={onOpenTable}
                                />
                                <span className="font-mono text-xs text-muted-foreground">
                                    ({reference.columns.join(', ')})
                                </span>
                                <span className="text-muted-foreground">
                                    {t('tables.structure.references_this')}
                                </span>
                            </li>
                        ))}
                    </ul>
                )}

                {relations > 0 &&
                    (showDiagram ? (
                        <RelationsDiagram
                            projectId={projectId}
                            table={structure.name}
                        />
                    ) : (
                        <Button
                            variant="outline"
                            size="sm"
                            className="self-start"
                            onClick={() => setShowDiagram(true)}
                        >
                            <WorkflowIcon />
                            {t('tables.structure.show_diagram')}
                        </Button>
                    ))}
            </Section>
        </div>
    );
}
