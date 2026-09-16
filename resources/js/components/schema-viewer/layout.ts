import ELK from 'elkjs/lib/elk.bundled.js';
import type { RelationshipEdgeType, TableNodeType } from './types';

const TABLE_WIDTH = 250;
const HEADER_HEIGHT = 40;
const ROW_HEIGHT = 30;
const MAX_ISOLATED_COLUMNS = 4;
const ISOLATED_COLUMN_GAP = 56;
const ISOLATED_ROW_GAP = 48;
const CONNECTED_GRAPH_GAP = 56;
const elk = new ELK();

const portId = (tableId: string, column: string, kind: 'source' | 'target') =>
    `${tableId}#${column}#${kind}`;

export async function getLayoutedElements(
    nodes: TableNodeType[],
    edges: RelationshipEdgeType[],
) {
    const connectedIds = new Set<string>();
    edges.forEach((edge) => {
        connectedIds.add(edge.source);
        connectedIds.add(edge.target);
    });

    const connectedNodes = nodes.filter((node) => connectedIds.has(node.id));
    const isolatedNodes = nodes.filter((node) => !connectedIds.has(node.id));
    const graph = {
        id: 'root',
        layoutOptions: {
            'elk.algorithm': 'layered',
            'elk.direction': 'RIGHT',
            'elk.layered.spacing.nodeNodeBetweenLayers': '56',
            'elk.spacing.nodeNode': '56',
            'elk.layered.layering.strategy': 'COFFMAN_GRAHAM',
            'elk.layered.coffmanGraham.layerBound': '4',
            'elk.edgeRouting': 'ORTHOGONAL',
            'elk.portConstraints': 'FIXED_POS',
            'elk.spacing.edgeNode': '24',
            'elk.spacing.edgeEdge': '14',
            'elk.layered.spacing.edgeNodeBetweenLayers': '24',
            'elk.layered.unnecessaryBendpoints': 'false',
        },
        children: connectedNodes.map((node) => ({
            id: node.id,
            width: TABLE_WIDTH,
            height: HEADER_HEIGHT + node.data.columns.length * ROW_HEIGHT,
            layoutOptions: { 'elk.portConstraints': 'FIXED_POS' },
            ports: node.data.columns.flatMap((column, index) => {
                const y = HEADER_HEIGHT + index * ROW_HEIGHT + ROW_HEIGHT / 2;

                return [
                    {
                        id: portId(node.id, column.name, 'target'),
                        x: 0,
                        y,
                        layoutOptions: { 'elk.port.side': 'WEST' },
                    },
                    {
                        id: portId(node.id, column.name, 'source'),
                        x: TABLE_WIDTH,
                        y,
                        layoutOptions: { 'elk.port.side': 'EAST' },
                    },
                ];
            }),
        })),
        edges: edges.map((edge) => ({
            id: edge.id,
            sources: [portId(edge.source, edge.sourceHandle ?? '', 'source')],
            targets: [portId(edge.target, edge.targetHandle ?? '', 'target')],
        })),
    };

    let layoutedConnectedNodes = connectedNodes;
    const layoutedEdges = edges;

    try {
        const layoutedGraph = await elk.layout(graph);
        layoutedConnectedNodes = connectedNodes.map((node) => {
            const elkNode = layoutedGraph.children?.find(
                (item) => item.id === node.id,
            );

            return {
                ...node,
                position: { x: elkNode?.x ?? 0, y: elkNode?.y ?? 0 },
            };
        });
    } catch (error) {
        console.error('Erreur de layout ELK', error);
    }

    const isolatedRows = Math.ceil(isolatedNodes.length / MAX_ISOLATED_COLUMNS);
    const isolatedRowHeight = isolatedNodes.reduce(
        (height, node) =>
            Math.max(
                height,
                HEADER_HEIGHT + node.data.columns.length * ROW_HEIGHT,
            ),
        0,
    );
    const isolatedGridHeight =
        isolatedRows > 0
            ? isolatedRows * isolatedRowHeight +
              (isolatedRows - 1) * ISOLATED_ROW_GAP
            : 0;
    const layoutedIsolatedNodes = isolatedNodes.map((node) => {
        const isolatedIndex = isolatedNodes.indexOf(node);
        const column = isolatedIndex % MAX_ISOLATED_COLUMNS;
        const row = Math.floor(isolatedIndex / MAX_ISOLATED_COLUMNS);

        const positioned = {
            ...node,
            position: {
                x: column * (TABLE_WIDTH + ISOLATED_COLUMN_GAP),
                y: row * (isolatedRowHeight + ISOLATED_ROW_GAP),
            },
        };

        return positioned;
    });
    const layoutedConnectedNodesWithOffset = layoutedConnectedNodes.map(
        (node) => ({
            ...node,
            position: {
                x: node.position.x,
                y:
                    node.position.y +
                    (isolatedGridHeight > 0
                        ? isolatedGridHeight + CONNECTED_GRAPH_GAP
                        : 0),
            },
        }),
    );
    const positionedById = new Map(
        [...layoutedConnectedNodesWithOffset, ...layoutedIsolatedNodes].map(
            (node) => [node.id, node],
        ),
    );

    return {
        layoutedNodes: nodes.map((node) => positionedById.get(node.id) ?? node),
        layoutedEdges,
    };
}
