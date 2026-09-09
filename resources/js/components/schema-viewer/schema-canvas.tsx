import {
    Background,
    BackgroundVariant,
    ControlButton,
    Controls,
    ReactFlow,
    ReactFlowProvider,
    useEdgesState,
    useNodesState,
} from '@xyflow/react';
import type { EdgeTypes, NodeTypes } from '@xyflow/react';
import { FileCode2Icon, ImageDownIcon, Loader2 } from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import '@xyflow/react/dist/style.css';
import { exportSchemaImage } from './export-schema';
import { getLayoutedElements } from './layout';
import { edgeTypes } from './relationship-edge';
import { schemaExportToDbSchema } from './schema-transform';
import { nodeTypes } from './table-node';
import { tableHandleId } from './types';
import type {
    RelationshipEdgeType,
    SchemaCanvasProps,
    TableNodeType,
} from './types';

const TABLE_COLORS = [
    'bg-emerald-500',
    'bg-indigo-500',
    'bg-blue-500',
    'bg-amber-500',
    'bg-teal-500',
    'bg-pink-500',
    'bg-rose-500',
    'bg-violet-500',
    'bg-cyan-500',
    'bg-orange-500',
    'bg-lime-500',
    'bg-fuchsia-500',
    'bg-sky-500',
    'bg-red-500',
    'bg-yellow-500',
    'bg-green-500',
    'bg-purple-500',
    'bg-slate-500',
    'bg-zinc-500',
    'bg-stone-500',
    'bg-emerald-600',
    'bg-blue-600',
    'bg-indigo-600',
    'bg-teal-600',
    'bg-pink-600',
    'bg-rose-600',
    'bg-violet-600',
    'bg-orange-600',
    'bg-cyan-600',
    'bg-lime-600',
] as const;

export type {
    DbSchema,
    SchemaColumn,
    SchemaDoctorFinding,
    SchemaExport,
    SchemaExportColumn,
    SchemaExportForeignKey,
    SchemaExportIndex,
    SchemaExportTable,
    SchemaRelationship,
    SchemaTable,
} from './types';
export type { SchemaCanvasProps } from './types';

function Canvas({ schema, showForeignKeys = true }: SchemaCanvasProps) {
    const [nodes, setNodes, onNodesChange] = useNodesState<TableNodeType>([]);
    const [edges, setEdges, onEdgesChange] =
        useEdgesState<RelationshipEdgeType>([]);
    const [exportingFormat, setExportingFormat] = useState<
        'png' | 'svg' | null
    >(null);

    const handleNodesChange = useCallback(
        (changes: Parameters<typeof onNodesChange>[0]) => {
            onNodesChange(changes);
        },
        [onNodesChange],
    );

    useEffect(() => {
        if (!schema) {
            return;
        }

        const dbSchema = schemaExportToDbSchema(schema);
        const initialNodes: TableNodeType[] = dbSchema.tables.map(
            (table, index) => ({
                id: table.id,
                type: 'table',
                position: { x: 0, y: 0 },
                data: {
                    name: table.name,
                    columns: table.columns,
                    color:
                        table.color ||
                        TABLE_COLORS[index % TABLE_COLORS.length],
                },
            }),
        );
        const initialEdges: RelationshipEdgeType[] = dbSchema.relationships.map(
            (relationship, index) => ({
                id: `e${index}`,
                source: relationship.source,
                target: relationship.target,
                sourceHandle: relationship.sourceColumn,
                targetHandle: relationship.targetColumn,
                type: 'relationship',
                data: {
                    sourceColumn: relationship.sourceColumn,
                },
            }),
        );

        getLayoutedElements(initialNodes, initialEdges).then(
            ({ layoutedNodes, layoutedEdges }) => {
                setNodes(layoutedNodes);
                setEdges(layoutedEdges);
            },
        );
    }, [schema, setEdges, setNodes]);

    const activeEdge = useMemo(
        () => edges.find((edge) => edge.selected),
        [edges],
    );
    const activeNode = useMemo(
        () => nodes.find((node) => node.selected),
        [nodes],
    );
    const { activeEdgeIds, highlightedFields } = useMemo(() => {
        const fields: Record<string, Set<string>> = {};
        const addField = (tableId: string, column?: string | null) => {
            if (!column) {
                return;
            }

            fields[tableId] ??= new Set();
            fields[tableId].add(column);
        };

        if (activeEdge) {
            addField(activeEdge.source, activeEdge.sourceHandle);
            addField(activeEdge.target, activeEdge.targetHandle);

            return {
                activeEdgeIds: new Set([activeEdge.id]),
                highlightedFields: fields,
            };
        }

        if (activeNode) {
            const relatedEdges = edges.filter(
                (edge) =>
                    edge.source === activeNode.id ||
                    edge.target === activeNode.id,
            );
            relatedEdges.forEach((edge) => {
                addField(edge.source, edge.sourceHandle);
                addField(edge.target, edge.targetHandle);
            });

            return {
                activeEdgeIds: new Set(relatedEdges.map((edge) => edge.id)),
                highlightedFields: fields,
            };
        }

        return { activeEdgeIds: null, highlightedFields: null };
    }, [activeEdge, activeNode, edges]);
    const displayNodes = useMemo(
        () =>
            nodes.map((node) => ({
                ...node,
                data: {
                    ...node.data,
                    highlightedColumns: highlightedFields?.[node.id]
                        ? Array.from(highlightedFields[node.id])
                        : undefined,
                    isActive: node.id === activeNode?.id,
                },
            })),
        [activeNode, highlightedFields, nodes],
    );
    const displayEdges = useMemo(
        () =>
            edges.map((edge) => {
                const sourceNode = nodes.find(
                    (node) => node.id === edge.source,
                );
                const targetNode = nodes.find(
                    (node) => node.id === edge.target,
                );
                const sourceIsLeft =
                    (sourceNode?.position.x ?? 0) <=
                    (targetNode?.position.x ?? 0);
                const sourceSide = sourceIsLeft ? 'right' : 'left';
                const targetSide = sourceIsLeft ? 'left' : 'right';

                return {
                    ...edge,
                    sourceHandle: edge.sourceHandle
                        ? tableHandleId(edge.sourceHandle, 'source', sourceSide)
                        : undefined,
                    targetHandle: edge.targetHandle
                        ? tableHandleId(edge.targetHandle, 'target', targetSide)
                        : undefined,
                    data: {
                        ...edge.data,
                        active: activeEdgeIds?.has(edge.id) ?? false,
                        bendPoints: undefined,
                        showForeignKeys,
                    },
                };
            }),
        [activeEdgeIds, edges, nodes, showForeignKeys],
    );
    const handleExport = useCallback(
        async (format: 'png' | 'svg') => {
            if (exportingFormat) {
                return;
            }

            setExportingFormat(format);

            try {
                await new Promise<void>((resolve) => {
                    requestAnimationFrame(() => {
                        requestAnimationFrame(() => resolve());
                    });
                });
                await exportSchemaImage(format, nodes, schema);
            } catch (error) {
                console.error("Erreur lors de l'export de l'image", error);
            } finally {
                setExportingFormat(null);
            }
        },
        [exportingFormat, nodes, schema],
    );

    return (
        <ReactFlow
            nodes={displayNodes}
            edges={displayEdges}
            onNodesChange={handleNodesChange}
            onEdgesChange={onEdgesChange}
            nodeTypes={nodeTypes as NodeTypes}
            edgeTypes={edgeTypes as EdgeTypes}
            nodesConnectable={false}
            fitView
            fitViewOptions={{ padding: 0.2 }}
        >
            <Background
                variant={BackgroundVariant.Dots}
                gap={16}
                size={1}
                color="var(--muted-foreground)"
            />
            <Controls
                showInteractive={false}
                className="overflow-hidden rounded-md border border-border bg-card"
            >
                <ControlButton
                    onClick={() => handleExport('png')}
                    disabled={exportingFormat !== null}
                    title="Exporter en PNG"
                >
                    {exportingFormat === 'png' ? (
                        <Loader2 className="max-h-3.5! max-w-3.5! animate-spin fill-none!" />
                    ) : (
                        <ImageDownIcon className="max-h-3.5! max-w-3.5! fill-none!" />
                    )}
                </ControlButton>
                <ControlButton
                    onClick={() => handleExport('svg')}
                    disabled={exportingFormat !== null}
                    title="Exporter en SVG"
                >
                    {exportingFormat === 'svg' ? (
                        <Loader2 className="max-h-3.5! max-w-3.5! animate-spin fill-none!" />
                    ) : (
                        <FileCode2Icon className="max-h-3.5! max-w-3.5! fill-none!" />
                    )}
                </ControlButton>
            </Controls>
        </ReactFlow>
    );
}

export default function SchemaCanvas({ schema }: SchemaCanvasProps) {
    return (
        <div className="h-full w-full bg-background">
            <style>{`
                @keyframes schema-edge-flow {
                    to { stroke-dashoffset: -20; }
                }

                .react-flow {
                    --xy-controls-button-background-color-default: hsl(var(--card));
                    --xy-controls-button-background-color-hover-default: hsl(var(--accent));
                    --xy-controls-button-color-default: hsl(var(--foreground));
                    --xy-controls-button-color-hover-default: hsl(var(--foreground));
                    --xy-controls-button-border-color-default: hsl(var(--border));
                    --xy-controls-box-shadow-default: none;
                }
            `}</style>
            <ReactFlowProvider>
                <Canvas schema={schema} />
            </ReactFlowProvider>
        </div>
    );
}
