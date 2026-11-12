import { Handle, Position } from '@xyflow/react';
import type { NodeProps, NodeTypes } from '@xyflow/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import {
    CircleAlertIcon,
    InfoIcon,
    KeyRound,
    Link2,
    TableIcon,
    TriangleAlertIcon,
} from 'lucide-react';
import { useContext } from 'react';
import { Badge } from '@/components/ui/badge';
import {
    ContextMenu,
    ContextMenuContent,
    ContextMenuTrigger,
} from '@/components/ui/context-menu';
import { cn } from '@/lib/utils';
import { SchemaTableMenuContext } from './table-menu';
import { TABLE_HANDLE, tableHandleId } from './types';
import type { FindingCounts, TableNodeType } from './types';

const SEVERITIES = [
    {
        severity: 'error',
        icon: CircleAlertIcon,
        className: 'bg-destructive/15 text-destructive',
    },
    {
        severity: 'warning',
        icon: TriangleAlertIcon,
        className: 'bg-warning/15 text-warning',
    },
    {
        severity: 'info',
        icon: InfoIcon,
        className: 'bg-sky-500/15 text-sky-600 dark:text-sky-400',
    },
] as const;

/**
 * The number of findings of the diagnostic of the table, with the color of the most serious one.
 */
function FindingsBadge({ findings }: { findings: FindingCounts }) {
    const { t, tChoice } = useLaravelReactI18n();
    const total = findings.error + findings.warning + findings.info;
    const worst = SEVERITIES.find(({ severity }) => findings[severity] > 0);

    if (!worst) {
        return null;
    }

    const Icon = worst.icon;
    const label = tChoice('erd.doctor.badge', total, {
        count: String(total),
    });

    return (
        <span
            className={cn(
                'ml-auto inline-flex h-5 shrink-0 items-center gap-1 rounded-full px-1.5 text-[11px] font-medium tabular-nums',
                worst.className,
            )}
            title={label}
            aria-label={label}
        >
            <Icon className="size-3" aria-hidden />
            {total}
            <span className="sr-only">{t('erd.doctor.label')}</span>
        </span>
    );
}

/**
 * The invisible ends of the relationships of a column, or of the table in its header: on both sides, as their
 * source and as their target. A column the user can link shows its right source handle when its row is hovered.
 */
function RelationshipHandles({
    name,
    connectable = false,
}: {
    name: string;
    connectable?: boolean;
}) {
    return (
        <>
            <Handle
                type="target"
                position={Position.Left}
                id={tableHandleId(name, 'target', 'left')}
                style={{ opacity: 0 }}
            />
            <Handle
                type="target"
                position={Position.Right}
                id={tableHandleId(name, 'target', 'right')}
                style={{ opacity: 0 }}
            />
            <Handle
                type="source"
                position={Position.Left}
                id={tableHandleId(name, 'source', 'left')}
                style={{ opacity: 0 }}
            />
            <Handle
                type="source"
                position={Position.Right}
                id={tableHandleId(name, 'source', 'right')}
                // The stylesheet of React Flow is outside the cascade layers: only important utilities restyle it.
                className={cn(
                    connectable &&
                        'size-2.5! rounded-full! border-2! border-background! bg-primary! opacity-0 transition-opacity group-hover/column:opacity-100',
                )}
                style={connectable ? undefined : { opacity: 0 }}
                isConnectable={connectable}
            />
        </>
    );
}

/**
 * How a column changed by a draft of the assistant is drawn.
 */
const DRAFT_COLUMNS = {
    added: 'bg-emerald-500/10',
    changed: 'bg-amber-500/10',
    dropped: 'bg-destructive/10 text-destructive line-through',
} as const;

export function TableNode({ data }: NodeProps<TableNodeType>) {
    const { t } = useLaravelReactI18n();
    const menu = useContext(SchemaTableMenuContext);

    const card = (
        <div
            className={cn(
                'min-w-55 overflow-hidden rounded-sm border border-border bg-card text-left shadow transition-shadow duration-300',
                data.isActive && 'ring ring-primary',
                data.draft === 'new' &&
                    'border-2 border-dashed border-emerald-500',
                data.draft === 'changed' && 'border-2 border-amber-500',
                data.draft === 'dropped' &&
                    'border-2 border-dashed border-destructive opacity-60',
            )}
        >
            <div className={cn('h-1', data.color)}></div>
            <div className="relative flex items-center gap-2 bg-primary/10 px-3 py-2 text-sm font-semibold">
                <TableIcon className="h-4 w-4 shrink-0" />
                <span
                    className={cn(
                        data.draft === 'dropped' &&
                            'text-destructive line-through',
                    )}
                >
                    {data.name}
                </span>
                {data.draft === 'new' && (
                    <span className="ml-auto rounded-full bg-emerald-500/15 px-1.5 text-[11px] font-medium text-emerald-700 dark:text-emerald-400">
                        {t('erd.assistant.draft.new')}
                    </span>
                )}
                {data.findings && <FindingsBadge findings={data.findings} />}
                <RelationshipHandles name={TABLE_HANDLE} />
            </div>
            <div>
                {data.columns.map((column) => {
                    const isHighlighted =
                        data.highlightedColumns?.includes(column.name) ?? false;

                    return (
                        <div
                            key={column.name}
                            className={cn(
                                'group/column relative flex items-center justify-between border-t border-border px-3 py-1.5 text-xs transition-colors duration-300',
                                isHighlighted && 'bg-sky-500/10',
                                column.draft && DRAFT_COLUMNS[column.draft],
                            )}
                        >
                            <span
                                className={cn(
                                    'flex items-center gap-1.5',
                                    column.draft !== 'dropped' &&
                                        'text-card-foreground',
                                )}
                            >
                                {column.pk && (
                                    <KeyRound
                                        size={11}
                                        className="shrink-0 text-amber-500"
                                    />
                                )}
                                {!column.pk && column.fk && (
                                    <Link2
                                        size={11}
                                        className="shrink-0 text-sky-500"
                                    />
                                )}
                                <span
                                    className={cn(column.pk && 'font-semibold')}
                                >
                                    {column.name}
                                </span>
                            </span>
                            <Badge
                                variant="outline"
                                className="ml-3 h-5 px-1.5 text-[10px] font-normal text-muted-foreground"
                            >
                                {column.type}
                            </Badge>
                            <RelationshipHandles
                                name={column.name}
                                connectable={data.connectable}
                            />
                        </div>
                    );
                })}
            </div>
        </div>
    );

    if (!menu) {
        return card;
    }

    return (
        <ContextMenu>
            <ContextMenuTrigger asChild>{card}</ContextMenuTrigger>
            <ContextMenuContent className="w-56">
                {menu(data.name)}
            </ContextMenuContent>
        </ContextMenu>
    );
}

export const nodeTypes: NodeTypes = { table: TableNode };
