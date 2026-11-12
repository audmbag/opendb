import { useLaravelReactI18n } from 'laravel-react-i18n';
import {
    ArrowRightIcon,
    KeyRoundIcon,
    PencilRulerIcon,
    WorkflowIcon,
} from 'lucide-react';
import { lazy, Suspense, useEffect, useState } from 'react';
import Button from '@/components/form/button';
import { Badge } from '@/components/reui/badge';
import type { SchemaExport } from '@/components/schema-viewer/types';
import { Skeleton } from '@/components/ui/skeleton';
import DatabaseSchemaController from '@/wayfinder/actions/App/Http/Controllers/Chat/DatabaseSchemaController';
import { requestTableData } from './api';
import type { TableStructure } from './types';

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
 * The columns of the table with their constraints, its indexes, and the tables it is linked to.
 */
export default function StructureView({
    projectId,
    structure,
    onOpenTable,
    onEditStructure,
}: Props) {
    const { t } = useLaravelReactI18n();
    const [showDiagram, setShowDiagram] = useState(false);
    const relations =
        structure.foreignKeys.length + structure.incomingReferences.length;

    return (
        <div className="flex min-h-0 flex-1 flex-col gap-8 overflow-y-auto p-4">
            {onEditStructure && (
                <div className="-mb-4 flex justify-end">
                    <Button variant="outline" size="sm" onClick={onEditStructure}>
                        <PencilRulerIcon />
                        {t('tables.workspace.edit_structure')}
                    </Button>
                </div>
            )}
            <Section title={t('ai.record.columns')} count={structure.columns.length}>
                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-left text-sm">
                        <thead className="bg-muted/60 text-muted-foreground">
                            <tr>
                                <th className="px-3 py-2 font-medium">{t('tables.designer.columns.name')}</th>
                                <th className="px-3 py-2 font-medium">{t('tables.designer.columns.type')}</th>
                                <th className="px-3 py-2 font-medium">NULL</th>
                                <th className="px-3 py-2 font-medium">
                                    {t('tables.structure.default')}
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    {t('tables.structure.constraints')}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {structure.columns.map((column) => (
                                <tr key={column.name} className="border-t">
                                    <td className="px-3 py-2 font-medium">
                                        <span className="inline-flex items-center gap-1.5">
                                            {column.primary && (
                                                <KeyRoundIcon
                                                    className="size-3.5 text-warning"
                                                    aria-label={t('tables.designer.columns.primary_key')}
                                                />
                                            )}
                                            {column.name}
                                        </span>
                                    </td>
                                    <td className="px-3 py-2 font-mono text-xs text-muted-foreground">
                                        {column.db_type}
                                    </td>
                                    <td className="px-3 py-2 text-muted-foreground">
                                        {column.nullable ? t('common.yes') : t('common.no')}
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
                                                    {t('tables.structure.auto_increment')}
                                                </Badge>
                                            )}
                                            {column.unique && (
                                                <Badge variant="outline">
                                                    {t('tables.records.unique')}
                                                </Badge>
                                            )}
                                            {column.generated && (
                                                <Badge variant="outline">
                                                    {t('tables.structure.generated')}
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
                                                            onOpen={onOpenTable}
                                                        />
                                                        .
                                                        {
                                                            column.references
                                                                .column
                                                        }
                                                    </span>
                                                </span>
                                            )}
                                        </span>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </Section>

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
                            <span className="font-medium">{t('tables.designer.columns.primary_key')}</span>
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
                                <Badge variant="outline">{t('tables.records.unique')}</Badge>
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
