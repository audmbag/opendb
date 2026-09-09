import ELK from 'elkjs/lib/elk.bundled.js';
import type { RelationshipEdgeType, TableNodeType } from './types';

const TABLE_WIDTH = 250;
const HEADER_HEIGHT = 40;
const ROW_HEIGHT = 30;
const ISOLATED_ROW_GAP = 64;
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
            'elk.layered.spacing.nodeNodeBetweenLayers': '80',
            'elk.spacing.nodeNode': '80',
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

    const connectedBounds = layoutedConnectedNodes.reduce(
        (bounds, node) => ({
            minX: Math.min(bounds.minX, node.position.x),
            minY: Math.min(bounds.minY, node.position.y),
        }),
        { minX: Infinity, minY: 0 },
    );
    const isolatedX = Number.isFinite(connectedBounds.minX)
        ? connectedBounds.minX - 80 - TABLE_WIDTH
        : 0;
    let cursorY = connectedBounds.minY;
    const layoutedIsolatedNodes = isolatedNodes.map((node) => {
        const positioned = { ...node, position: { x: isolatedX, y: cursorY } };
        cursorY +=
            HEADER_HEIGHT +
            node.data.columns.length * ROW_HEIGHT +
            ISOLATED_ROW_GAP;

        return positioned;
    });
    const positionedById = new Map(
        [...layoutedConnectedNodes, ...layoutedIsolatedNodes].map((node) => [
            node.id,
            node,
        ]),
    );

    return {
        layoutedNodes: nodes.map((node) => positionedById.get(node.id) ?? node),
        layoutedEdges,
    };
}
