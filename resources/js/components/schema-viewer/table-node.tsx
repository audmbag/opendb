import { Handle, Position } from '@xyflow/react';
import type { NodeProps, NodeTypes } from '@xyflow/react';
import { KeyRound, Link2, TableIcon } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import { tableHandleId } from './types';
import type { TableNodeType } from './types';

export function TableNode({ data }: NodeProps<TableNodeType>) {
    return (
        <div
            className={cn(
                'min-w-55 overflow-hidden rounded-sm border border-border bg-card text-left shadow transition-shadow duration-300',
                data.isActive && 'ring ring-primary',
            )}
        >
            <div className={cn('h-1', data.color)}></div>
            <div className="flex items-center gap-2 bg-primary/10 px-3 py-2 text-sm font-semibold">
                <TableIcon className="h-4 w-4" />
                {data.name}
            </div>
            <div>
                {data.columns.map((column) => {
                    const isHighlighted =
                        data.highlightedColumns?.includes(column.name) ?? false;

                    return (
                        <div
                            key={column.name}
                            className={cn(
                                'relative flex items-center justify-between border-t border-border px-3 py-1.5 text-xs transition-colors duration-300',
                                isHighlighted && 'bg-sky-500/10',
                            )}
                        >
                            <Handle
                                type="target"
                                position={Position.Left}
                                id={tableHandleId(
                                    column.name,
                                    'target',
                                    'left',
                                )}
                                style={{ opacity: 0 }}
                            />
                            <span className="flex items-center gap-1.5 text-card-foreground">
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
                            <Handle
                                type="target"
                                position={Position.Right}
                                id={tableHandleId(
                                    column.name,
                                    'target',
                                    'right',
                                )}
                                style={{ opacity: 0 }}
                            />
                            <Handle
                                type="source"
                                position={Position.Left}
                                id={tableHandleId(
                                    column.name,
                                    'source',
                                    'left',
                                )}
                                style={{ opacity: 0 }}
                            />
                            <Handle
                                type="source"
                                position={Position.Right}
                                id={tableHandleId(
                                    column.name,
                                    'source',
                                    'right',
                                )}
                                style={{ opacity: 0 }}
                            />
                        </div>
                    );
                })}
            </div>
        </div>
    );
}

export const nodeTypes: NodeTypes = { table: TableNode };
