import { useLaravelReactI18n } from 'laravel-react-i18n';
import {
    GitBranchPlusIcon,
    KeyRoundIcon,
    Link2Icon,
    TableIcon,
    XIcon,
} from 'lucide-react';
import { useEffect, useMemo, useRef } from 'react';
import type { ReactNode } from 'react';
import Button from '@/components/form/button';
import { Badge } from '@/components/reui/badge';
import { cn } from '@/lib/utils';
import { findingText, IMPLICIT_RELATION } from './diagnostics';
import { SEVERITY_ICONS } from './diagnostics-popover';
import type {
    SchemaDoctorFinding,
    SchemaExport,
    SchemaExportTable,
} from './types';

type Props = {
    table: SchemaExportTable;
    /**
     * The whole diagram, to find the tables referencing this one.
     */
    schema: SchemaExport;
    /**
     * Bring a linked table into the view.
     */
    onShowTable: (table: string) => void;
    onClose: () => void;
    /**
     * The buttons opening the table elsewhere in the project.
     */
    actions?: ReactNode;
    /**
     * The findings of the diagnostic about this table, once the doctor has answered.
     */
    findings?: SchemaDoctorFinding[];
    /**
     * Add the foreign key a column without constraint likely stands for.
     */
    onCreateRelation?: (finding: SchemaDoctorFinding) => void;
};

function Section({
    title,
    count,
    children,
}: {
    title: string;
    count: number;
    children: ReactNode;
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
    onShow,
}: {
    table: string;
    onShow: (table: string) => void;
}) {
    return (
        <button
            type="button"
            onClick={() => onShow(table)}
            className="font-medium underline-offset-4 hover:text-sidebar-primary hover:underline"
        >
            {table}
        </button>
    );
}

/**
 * The table selected in the diagram: its columns with their type, nullability and default value, its indexes, and
 * the foreign keys from and to it. Everything comes from the schema already drawn, without another request.
 */
export default function TableInspector({
    table,
    schema,
    onShowTable,
    onClose,
    actions,
    findings,
    onCreateRelation,
}: Props) {
    const { t } = useLaravelReactI18n();
    const rootRef = useRef<HTMLElement>(null);

    // The panel scrolls by itself: another table is read from its top, not where the previous one was left.
    useEffect(() => {
        rootRef.current?.parentElement?.scrollTo({ top: 0 });
    }, [table.name]);

    const foreignColumns = useMemo(
        () =>
            new Set(
                table.foreign_keys.flatMap((foreignKey) => foreignKey.columns),
            ),
        [table],
    );
    const incomingReferences = useMemo(
        () =>
            schema.flatMap((other) =>
                other.foreign_keys
                    .filter(
                        (foreignKey) =>
                            foreignKey.references_table === table.name,
                    )
                    .map((foreignKey) => ({
                        table: other.name,
                        name: foreignKey.name,
                        columns: foreignKey.columns,
                    })),
            ),
        [schema, table.name],
    );
    const relations = table.foreign_keys.length + incomingReferences.length;

    return (
        <aside
            ref={rootRef}
            aria-label={table.name}
            className="flex min-h-full flex-col gap-6 p-4"
        >
            <div className="flex flex-col gap-3">
                <div className="flex items-center gap-2">
                    <TableIcon className="size-4 shrink-0 text-muted-foreground" />
                    <h2
                        className="min-w-0 flex-1 truncate font-medium"
                        title={table.name}
                    >
                        {table.name}
                    </h2>
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        onClick={onClose}
                        aria-label={t('common.close')}
                        tooltip={t('common.close')}
                    >
                        <XIcon />
                    </Button>
                </div>
                {actions && (
                    <div className="flex flex-wrap gap-1.5">{actions}</div>
                )}
            </div>

            {findings && findings.length > 0 && (
                <Section title={t('erd.doctor.label')} count={findings.length}>
                    <ul className="flex flex-col divide-y rounded-lg border text-sm">
                        {findings.map((finding) => {
                            const { icon: Icon, className } =
                                SEVERITY_ICONS[finding.severity];
                            const hint = findingText(t, finding, 'hint');

                            return (
                                <li
                                    key={finding.fingerprint}
                                    className="flex flex-col gap-1.5 px-3 py-2"
                                >
                                    <span className="flex items-start gap-1.5">
                                        <Icon
                                            className={cn(
                                                'mt-0.5 size-3.5 shrink-0',
                                                className,
                                            )}
                                            aria-label={t(
                                                `erd.doctor.severity.${finding.severity}`,
                                            )}
                                        />
                                        <span className="min-w-0 flex-1 font-medium">
                                            {findingText(t, finding, 'title')}
                                        </span>
                                    </span>
                                    {(finding.column ||
                                        finding.confidence !== 'high') && (
                                        <span className="flex flex-wrap items-center gap-2">
                                            {finding.column && (
                                                <span className="font-mono text-xs text-muted-foreground">
                                                    {finding.column}
                                                    {finding.references &&
                                                        ` → ${finding.references.table}.${finding.references.column}`}
                                                </span>
                                            )}
                                            {finding.confidence !== 'high' && (
                                                <Badge variant="outline">
                                                    {t('erd.doctor.heuristic')}
                                                </Badge>
                                            )}
                                        </span>
                                    )}
                                    {hint && (
                                        <p className="text-xs text-muted-foreground">
                                            {hint}
                                        </p>
                                    )}
                                    {finding.code === IMPLICIT_RELATION &&
                                        finding.references &&
                                        onCreateRelation && (
                                            <Button
                                                variant="outline"
                                                size="xs"
                                                className="self-start"
                                                onClick={() =>
                                                    onCreateRelation(finding)
                                                }
                                            >
                                                <GitBranchPlusIcon />
                                                {t('erd.relation.create')}
                                            </Button>
                                        )}
                                </li>
                            );
                        })}
                    </ul>
                </Section>
            )}

            <Section
                title={t('ai.record.columns')}
                count={table.columns.length}
            >
                <ul className="flex flex-col divide-y rounded-lg border text-sm">
                    {table.columns.map((column) => (
                        <li
                            key={column.name}
                            className="flex flex-col gap-0.5 px-3 py-2"
                        >
                            <span className="flex min-w-0 items-center gap-1.5">
                                {table.primary_key.includes(column.name) && (
                                    <KeyRoundIcon
                                        className="size-3.5 shrink-0 text-warning"
                                        aria-label={t(
                                            'tables.designer.columns.primary_key',
                                        )}
                                    />
                                )}
                                {!table.primary_key.includes(column.name) &&
                                    foreignColumns.has(column.name) && (
                                        <Link2Icon
                                            className="size-3.5 shrink-0 text-sky-500"
                                            aria-label={t(
                                                'tables.list.foreign_key',
                                            )}
                                        />
                                    )}
                                <span className="min-w-0 flex-1 truncate font-medium">
                                    {column.name}
                                </span>
                                {column.nullable && (
                                    <span className="font-mono text-[10px] text-muted-foreground">
                                        NULL
                                    </span>
                                )}
                            </span>
                            <span className="flex min-w-0 items-baseline gap-2 font-mono text-xs text-muted-foreground">
                                <span className="truncate">{column.type}</span>
                                {column.default !== null && (
                                    <span
                                        className="min-w-0 truncate"
                                        title={String(column.default)}
                                    >
                                        {t('tables.structure.default')} :{' '}
                                        {String(column.default)}
                                    </span>
                                )}
                            </span>
                        </li>
                    ))}
                </ul>
            </Section>

            <Section
                title={t('tables.list.sections.indexes')}
                count={
                    table.indexes.length + (table.primary_key.length ? 1 : 0)
                }
            >
                <ul className="flex flex-col divide-y rounded-lg border text-sm">
                    {table.primary_key.length > 0 && (
                        <li className="flex flex-wrap items-center gap-x-3 gap-y-1 px-3 py-2">
                            <span className="font-medium">
                                {t('tables.designer.columns.primary_key')}
                            </span>
                            <span className="font-mono text-xs text-muted-foreground">
                                ({table.primary_key.join(', ')})
                            </span>
                        </li>
                    )}
                    {table.indexes.map((index) => (
                        <li
                            key={index.name}
                            className="flex flex-wrap items-center gap-x-3 gap-y-1 px-3 py-2"
                        >
                            <span
                                className="min-w-0 truncate font-medium"
                                title={index.name}
                            >
                                {index.name}
                            </span>
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
                    {table.indexes.length === 0 &&
                        table.primary_key.length === 0 && (
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
                        {table.foreign_keys.map((foreignKey) => (
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
                                    onShow={onShowTable}
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
                        {incomingReferences.map((reference) => (
                            <li
                                key={`in-${reference.table}-${reference.name}-${reference.columns.join()}`}
                                className="flex flex-wrap items-center gap-x-2 gap-y-1 px-3 py-2"
                            >
                                <TableLink
                                    table={reference.table}
                                    onShow={onShowTable}
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
            </Section>
        </aside>
    );
}
