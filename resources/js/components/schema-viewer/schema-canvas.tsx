import {
    ReactFlowProvider,
    useEdgesState,
    useNodesInitialized,
    useNodesState,
} from '@xyflow/react';
import type { EdgeTypes, NodeTypes } from '@xyflow/react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import FlowCanvas from '@/components/flow/flow-canvas';
import { getLayoutedNodes } from './layout';
import { edgeTypes } from './relationship-edge';
import { schemaExportToDbSchema } from './schema-transform';
import { nodeTypes } from './table-node';
import { tableHandleId } from './types';
import type {
    RelationshipEdgeType,
    SchemaCanvasProps,
    SchemaExport,
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
    const [layoutedSchema, setLayoutedSchema] = useState<SchemaExport | null>(
        null,
    );
    const isLayouted = schema !== null && layoutedSchema === schema;
    const nodesInitialized = useNodesInitialized();

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

        setNodes(initialNodes);
        setEdges(initialEdges);
    }, [schema, setEdges, setNodes]);

    /**
     * The tables are first rendered hidden so React Flow measures them, then laid out with their real size.
     */
    useEffect(() => {
        if (!nodesInitialized || isLayouted) {
            return;
        }

        let isCancelled = false;

        getLayoutedNodes(nodes, edges).then((layoutedNodes) => {
            if (isCancelled) {
                return;
            }

            setNodes(layoutedNodes);
            setLayoutedSchema(schema);
        });

        return () => {
            isCancelled = true;
        };
    }, [
        edges,
        isLayouted,
        nodes,
        nodesInitialized,
        schema,
        setNodes,
    ]);

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
                const isSelfReference = edge.source === edge.target;
                const sourceIsLeft =
                    (sourceNode?.position.x ?? 0) <=
                    (targetNode?.position.x ?? 0);
                const sourceSide = sourceIsLeft ? 'right' : 'left';
                const targetSide =
                    sourceIsLeft && !isSelfReference ? 'left' : 'right';

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

    return (
        <FlowCanvas<TableNodeType, RelationshipEdgeType>
            label="Schéma de la base de données"
            exportFileName="schema"
            isReady={isLayouted}
            className="h-full"
            canvasClassName="h-full"
            nodes={displayNodes}
            edges={displayEdges}
            onNodesChange={handleNodesChange}
            onEdgesChange={onEdgesChange}
            nodeTypes={nodeTypes as NodeTypes}
            edgeTypes={edgeTypes as EdgeTypes}
            nodesConnectable={false}
            minZoom={0.1}
        />
    );
}

export default function SchemaCanvas({
    schema,
    showForeignKeys,
}: SchemaCanvasProps) {
    return (
        <div className="h-full w-full">
            <ReactFlowProvider>
                <Canvas schema={schema} showForeignKeys={showForeignKeys} />
            </ReactFlowProvider>
        </div>
    );
}
