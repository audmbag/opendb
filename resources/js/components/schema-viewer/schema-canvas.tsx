import {
    MiniMap,
    ReactFlowProvider,
    useEdgesState,
    useNodesInitialized,
    useNodesState,
    useReactFlow,
} from '@xyflow/react';
import type {
    Connection,
    Edge,
    EdgeTypes,
    NodeTypes,
    OnConnect,
} from '@xyflow/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import FlowCanvas, {
    prefersReducedMotion,
} from '@/components/flow/flow-canvas';
import { cn } from '@/lib/utils';
import { getLayoutedNodes } from './layout';
import { edgeTypes } from './relationship-edge';
import { schemaExportToDbSchema } from './schema-transform';
import { nodeTypes } from './table-node';
import { handleColumn, TABLE_HANDLE, tableHandleId } from './types';
import type {
    DbSchema,
    Point,
    RelationshipEdgeType,
    SchemaCanvasProps,
    SchemaColorBy,
    SchemaDetail,
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

/**
 * The color of a table alone in its group.
 */
const UNGROUPED_COLOR = 'bg-slate-500';

/**
 * The space between the tables placed by the user and those laid out below them.
 */
const UNPLACED_GAP = 120;

export type {
    DbSchema,
    SchemaColumn,
    SchemaDiagnostics,
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

/**
 * The columns a table shows at the given level of detail. The keys keep the columns referenced by a foreign key, so
 * that every relationship still has its two ends.
 */
function visibleColumns(
    dbSchema: DbSchema,
    detail: SchemaDetail,
): Map<string, DbSchema['tables'][number]['columns']> {
    if (detail === 'names') {
        return new Map(dbSchema.tables.map((table) => [table.id, []]));
    }

    if (detail === 'all') {
        return new Map(
            dbSchema.tables.map((table) => [table.id, table.columns]),
        );
    }

    const referenced = new Set(
        dbSchema.relationships.map(
            (relationship) =>
                `${relationship.target}.${relationship.targetColumn}`,
        ),
    );

    return new Map(
        dbSchema.tables.map((table) => [
            table.id,
            table.columns.filter(
                (column) =>
                    column.pk ||
                    column.fk ||
                    referenced.has(`${table.id}.${column.name}`),
            ),
        ]),
    );
}

/**
 * The color of each table: its own, or that of its group, the tables sharing the prefix of their name or linked by
 * foreign keys. A table alone in its group is grey.
 */
function tableColors(
    dbSchema: DbSchema,
    colorBy: SchemaColorBy,
): Map<string, string> {
    if (colorBy === 'table') {
        return new Map(
            dbSchema.tables.map((table, index) => [
                table.id,
                table.color || TABLE_COLORS[index % TABLE_COLORS.length],
            ]),
        );
    }

    let groupOf: (table: string) => string | null;

    if (colorBy === 'prefix') {
        groupOf = (table) =>
            table.includes('_') ? table.slice(0, table.indexOf('_')) : null;
    } else {
        const parents = new Map(
            dbSchema.tables.map((table) => [table.id, table.id]),
        );
        const root = (table: string): string => {
            let current = table;

            while (parents.get(current) !== current) {
                current = parents.get(current) ?? current;
            }

            parents.set(table, current);

            return current;
        };

        dbSchema.relationships.forEach((relationship) => {
            if (
                parents.has(relationship.source) &&
                parents.has(relationship.target)
            ) {
                parents.set(
                    root(relationship.source),
                    root(relationship.target),
                );
            }
        });

        groupOf = root;
    }

    const groups = dbSchema.tables.map((table) => groupOf(table.id));
    const sizes = new Map<string, number>();

    groups.forEach(
        (group) =>
            group !== null && sizes.set(group, (sizes.get(group) ?? 0) + 1),
    );

    const colors = new Map<string, string>();

    return new Map(
        dbSchema.tables.map((table, index) => {
            const group = groups[index];

            if (group === null || (sizes.get(group) ?? 0) < 2) {
                return [table.id, UNGROUPED_COLOR];
            }

            if (!colors.has(group)) {
                colors.set(
                    group,
                    TABLE_COLORS[colors.size % TABLE_COLORS.length],
                );
            }

            return [table.id, colors.get(group)!];
        }),
    );
}

/**
 * The tables laid out by ELK, those the user placed put back where they were. The others keep their arrangement,
 * moved below the placed ones so that nothing overlaps.
 */
function withSavedPositions(
    nodes: TableNodeType[],
    saved: Record<string, Point> | undefined,
): TableNodeType[] {
    const placed = nodes.filter((node) => saved?.[node.id]);

    if (!saved || !placed.length) {
        return nodes;
    }

    const unplaced = nodes.filter((node) => !saved[node.id]);
    const left = Math.min(...placed.map((node) => saved[node.id].x));
    const bottom = Math.max(
        ...placed.map(
            (node) => saved[node.id].y + (node.measured?.height ?? 0),
        ),
    );
    const unplacedLeft = Math.min(...unplaced.map((node) => node.position.x));
    const unplacedTop = Math.min(...unplaced.map((node) => node.position.y));

    return nodes.map((node) => ({
        ...node,
        position: saved[node.id] ?? {
            x: node.position.x - unplacedLeft + left,
            y: node.position.y - unplacedTop + bottom + UNPLACED_GAP,
        },
    }));
}

function Canvas({
    schema,
    showForeignKeys = true,
    detail = 'all',
    centeredTable = null,
    selectedTable,
    onTableSelect,
    aside,
    zoomOnScroll,
    savedPositions,
    onPositionsChange,
    showCardinality = false,
    colorBy = 'table',
    minimap = false,
    findingsByTable,
    implicitRelationships,
    onConnectColumns,
    keepLayout = false,
}: SchemaCanvasProps) {
    const { t } = useLaravelReactI18n();
    const { fitView, getNodes } = useReactFlow<
        TableNodeType,
        RelationshipEdgeType
    >();
    const [nodes, setNodes, onNodesChange] = useNodesState<TableNodeType>([]);
    const [edges, setEdges, onEdgesChange] =
        useEdgesState<RelationshipEdgeType>([]);
    const [layouted, setLayouted] = useState<{
        schema: SchemaExport;
        detail: SchemaDetail;
    } | null>(null);
    const isLayouted =
        schema !== null &&
        layouted?.schema === schema &&
        layouted.detail === detail;
    const nodesInitialized = useNodesInitialized();
    const onTableSelectRef = useRef(onTableSelect);
    const selectedTableRef = useRef(selectedTable);
    // Read when the diagram is laid out: saving a moved table must not lay it out again.
    const savedPositionsRef = useRef(savedPositions);
    const onPositionsChangeRef = useRef(onPositionsChange);
    // Where each table was drawn last, at the level of detail it was laid out for.
    const drawnPositionsRef = useRef<{
        detail: SchemaDetail;
        positions: Record<string, Point>;
    } | null>(null);
    const keepLayoutRef = useRef(keepLayout);

    useEffect(() => {
        onTableSelectRef.current = onTableSelect;
        selectedTableRef.current = selectedTable;
        savedPositionsRef.current = savedPositions;
        onPositionsChangeRef.current = onPositionsChange;
        keepLayoutRef.current = keepLayout;
    }, [
        keepLayout,
        onPositionsChange,
        onTableSelect,
        savedPositions,
        selectedTable,
    ]);

    const dbSchema = useMemo(
        () => (schema ? schemaExportToDbSchema(schema) : null),
        [schema],
    );
    const colors = useMemo(
        () => (dbSchema ? tableColors(dbSchema, colorBy) : null),
        [colorBy, dbSchema],
    );

    const handleNodesChange = useCallback(
        (changes: Parameters<typeof onNodesChange>[0]) => {
            onNodesChange(changes);
        },
        [onNodesChange],
    );

    useEffect(() => {
        if (!dbSchema) {
            return;
        }

        const columns = visibleColumns(dbSchema, detail);
        const initialNodes: TableNodeType[] = dbSchema.tables.map(
            (table, index) => ({
                id: table.id,
                type: 'table',
                position: { x: 0, y: 0 },
                data: {
                    name: table.name,
                    draft: table.draft,
                    columns: columns.get(table.id) ?? [],
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
                sourceHandle:
                    detail === 'names'
                        ? TABLE_HANDLE
                        : relationship.sourceColumn,
                targetHandle:
                    detail === 'names'
                        ? TABLE_HANDLE
                        : relationship.targetColumn,
                type: 'relationship',
                data: {
                    sourceColumn: relationship.sourceColumn,
                    draft: relationship.draft,
                    cardinality: {
                        unique: relationship.unique ?? false,
                        nullable: relationship.nullable ?? false,
                    },
                },
            }),
        );

        // The selected table stays selected when the diagram is drawn again at another level of detail.
        setNodes((current) => {
            const selected = new Set(
                selectedTableRef.current !== undefined
                    ? [selectedTableRef.current]
                    : current
                          .filter((node) => node.selected)
                          .map((node) => node.id),
            );

            return initialNodes.map((node) =>
                selected.has(node.id) ? { ...node, selected: true } : node,
            );
        });
        setEdges(initialEdges);
    }, [dbSchema, detail, setEdges, setNodes]);

    /**
     * The tables are first rendered hidden so React Flow measures them, then laid out with their real size.
     */
    useEffect(() => {
        // Every table must be measured: right after the tables are replaced, the store can still say they are.
        const isMeasured =
            nodes.length > 0 && nodes.every((node) => node.measured?.width);

        if (!nodesInitialized || !isMeasured || isLayouted || !schema) {
            return;
        }

        let isCancelled = false;

        getLayoutedNodes(nodes, edges).then((layoutedNodes) => {
            if (isCancelled) {
                return;
            }

            const drawn = drawnPositionsRef.current;
            const placedNodes = withSavedPositions(layoutedNodes, {
                // The tables already drawn stay where they are: only the new ones are laid out.
                ...(keepLayoutRef.current && drawn?.detail === detail
                    ? drawn.positions
                    : {}),
                ...savedPositionsRef.current,
            });

            drawnPositionsRef.current = {
                detail,
                positions: Object.fromEntries(
                    placedNodes.map((node) => [node.id, node.position]),
                ),
            };
            setNodes(placedNodes);
            setLayouted({ schema, detail });
        });

        return () => {
            isCancelled = true;
        };
    }, [detail, edges, isLayouted, nodes, nodesInitialized, schema, setNodes]);

    /**
     * Follow the table the page selects, once the diagram is laid out.
     */
    useEffect(() => {
        if (selectedTable === undefined || !isLayouted) {
            return;
        }

        setNodes((current) =>
            current.map((node) =>
                node.selected === (node.id === selectedTable)
                    ? node
                    : { ...node, selected: node.id === selectedTable },
            ),
        );
    }, [isLayouted, selectedTable, setNodes]);

    /**
     * Frame the requested table, or every table without one. Each request is a new object, so that asking for the same
     * table again frames it again.
     */
    const fitOnCenteredTable = useCallback(() => {
        if (!centeredTable) {
            fitView({ padding: 0.1 });

            return;
        }

        const names = [centeredTable.name, ...(centeredTable.others ?? [])];

        fitView({
            nodes: names.map((id) => ({ id })),
            padding: names.length > 1 ? 0.1 : 0.4,
            maxZoom: 1.1,
            duration: prefersReducedMotion() ? 0 : 300,
        });
    }, [centeredTable, fitView]);

    /**
     * The place of every table drawn, once the user dropped one.
     */
    const handleNodeDragStop = useCallback(() => {
        const positions = Object.fromEntries(
            getNodes().map((node) => [
                node.id,
                {
                    x: Math.round(node.position.x),
                    y: Math.round(node.position.y),
                },
            ]),
        );

        if (drawnPositionsRef.current) {
            drawnPositionsRef.current = {
                ...drawnPositionsRef.current,
                positions,
            };
        }

        onPositionsChangeRef.current?.(positions);
    }, [getNodes]);

    /**
     * A link drawn from a column to the column it should reference: not from or to a table shown without its
     * columns, not from a column to itself, and not doubling a foreign key.
     */
    const isValidConnection = useCallback(
        (connection: Connection | Edge) => {
            const sourceColumn = handleColumn(connection.sourceHandle ?? '');
            const targetColumn = handleColumn(connection.targetHandle ?? '');

            if (
                !sourceColumn ||
                !targetColumn ||
                sourceColumn === TABLE_HANDLE ||
                targetColumn === TABLE_HANDLE
            ) {
                return false;
            }

            if (
                connection.source === connection.target &&
                sourceColumn === targetColumn
            ) {
                return false;
            }

            return !edges.some(
                (edge) =>
                    edge.source === connection.source &&
                    edge.target === connection.target &&
                    edge.sourceHandle === sourceColumn &&
                    edge.targetHandle === targetColumn,
            );
        },
        [edges],
    );

    const handleConnect = useCallback<OnConnect>(
        (connection) =>
            onConnectColumns?.(
                {
                    table: connection.source,
                    column: handleColumn(connection.sourceHandle ?? ''),
                },
                {
                    table: connection.target,
                    column: handleColumn(connection.targetHandle ?? ''),
                },
            ),
        [onConnectColumns],
    );

    /**
     * The relationships guessed from the name of a column, between two tables drawn. They end on the header of a
     * table whose column is not shown.
     */
    const implicitEdges = useMemo<RelationshipEdgeType[]>(() => {
        if (!implicitRelationships?.length || !isLayouted) {
            return [];
        }

        const shownColumns = new Map(
            nodes.map((node) => [
                node.id,
                new Set(node.data.columns.map((column) => column.name)),
            ]),
        );
        const handle = (table: string, column: string) =>
            shownColumns.get(table)?.has(column) ? column : TABLE_HANDLE;

        return implicitRelationships
            .filter(
                (relationship) =>
                    shownColumns.has(relationship.source) &&
                    shownColumns.has(relationship.target),
            )
            .map((relationship) => ({
                id: `implicit-${relationship.source}-${relationship.sourceColumn}`,
                source: relationship.source,
                target: relationship.target,
                sourceHandle: handle(
                    relationship.source,
                    relationship.sourceColumn,
                ),
                targetHandle: handle(
                    relationship.target,
                    relationship.targetColumn,
                ),
                type: 'relationship',
                selectable: false,
                data: {
                    sourceColumn: relationship.sourceColumn,
                    implicit: true,
                },
            }));
    }, [implicitRelationships, isLayouted, nodes]);

    const activeEdge = useMemo(
        () => edges.find((edge) => edge.selected),
        [edges],
    );
    const activeNode = useMemo(
        () => nodes.find((node) => node.selected),
        [nodes],
    );
    const activeNodeId = activeNode?.id ?? null;

    useEffect(() => {
        onTableSelectRef.current?.(activeNodeId);
    }, [activeNodeId]);
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
                    color: colors?.get(node.id) ?? node.data.color,
                    highlightedColumns: highlightedFields?.[node.id]
                        ? Array.from(highlightedFields[node.id])
                        : undefined,
                    isActive: node.id === activeNode?.id,
                    findings: findingsByTable?.[node.id],
                    connectable:
                        onConnectColumns !== undefined && detail !== 'names',
                },
            })),
        [
            activeNode,
            colors,
            detail,
            findingsByTable,
            highlightedFields,
            nodes,
            onConnectColumns,
        ],
    );
    const displayEdges = useMemo(
        () =>
            [...edges, ...implicitEdges].map((edge) => {
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
                        cardinality: showCardinality
                            ? edge.data?.cardinality
                            : undefined,
                    },
                };
            }),
        [
            activeEdgeIds,
            edges,
            implicitEdges,
            nodes,
            showCardinality,
            showForeignKeys,
        ],
    );

    return (
        <FlowCanvas<TableNodeType, RelationshipEdgeType>
            label={t('flow.database_schema')}
            exportFileName="schema"
            isReady={isLayouted}
            // On one line, the panel is as tall as the diagram and scrolls by itself: wrapping would let it grow the page.
            className={cn('h-full', aside && 'flex-nowrap overflow-hidden')}
            canvasClassName="h-full"
            nodes={displayNodes}
            edges={displayEdges}
            onNodesChange={handleNodesChange}
            onEdgesChange={onEdgesChange}
            nodeTypes={nodeTypes as NodeTypes}
            edgeTypes={edgeTypes as EdgeTypes}
            nodesConnectable={onConnectColumns !== undefined}
            onConnect={onConnectColumns ? handleConnect : undefined}
            isValidConnection={isValidConnection}
            onNodeDragStop={
                onPositionsChange || keepLayout ? handleNodeDragStop : undefined
            }
            minZoom={0.1}
            aside={aside}
            asideClassName="max-w-96"
            zoomOnScroll={zoomOnScroll}
            fitOnResize={!zoomOnScroll}
            onFitView={fitOnCenteredTable}
        >
            {minimap && <MiniMap pannable zoomable />}
        </FlowCanvas>
    );
}

export default function SchemaCanvas(props: SchemaCanvasProps) {
    return (
        <div className="h-full w-full">
            <ReactFlowProvider>
                <Canvas {...props} />
            </ReactFlowProvider>
        </div>
    );
}
