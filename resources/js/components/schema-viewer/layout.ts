import ELK from 'elkjs/lib/elk.bundled.js';
import type { ElkNode } from 'elkjs/lib/elk.bundled.js';
import type { RelationshipEdgeType, TableNodeType } from './types';

/**
 * Height of a column row in TableNode: vertical padding, badge and top border.
 */
const ROW_HEIGHT = 33;
const HEADER_HEIGHT = 40;
const FALLBACK_WIDTH = 250;
const LAYER_SPACING = 120;
const NODE_SPACING = 48;
const COMPONENT_SPACING = 96;

/**
 * Width / height ratio aimed at, so the diagram fills a landscape canvas instead of a tall column.
 */
const TARGET_ASPECT_RATIO = 1.6;

const elk = new ELK();

type Size = { width: number; height: number };

const portId = (tableId: string, column: string, side: 'east' | 'west'): string =>
    `${tableId}#${column}#${side}`;

/**
 * The size of a table as rendered by React Flow, estimated when it has not been measured yet.
 */
function tableSize(node: TableNodeType): Size {
    return {
        width: node.measured?.width ?? FALLBACK_WIDTH,
        height:
            node.measured?.height ??
            HEADER_HEIGHT + node.data.columns.length * ROW_HEIGHT,
    };
}

/**
 * The maximum number of tables stacked in one column, chosen so the whole diagram gets close to the target aspect ratio.
 */
function maxTablesPerLayer(sizes: Size[]): number {
    if (sizes.length === 0) {
        return 1;
    }

    const averageWidth =
        sizes.reduce((total, size) => total + size.width, 0) / sizes.length +
        LAYER_SPACING;
    const averageHeight =
        sizes.reduce((total, size) => total + size.height, 0) / sizes.length +
        NODE_SPACING;

    return Math.max(
        2,
        Math.round(
            Math.sqrt(
                (sizes.length * averageWidth) /
                    (averageHeight * TARGET_ASPECT_RATIO),
            ),
        ),
    );
}

/**
 * Place the tables in columns from the referenced tables (left) to the tables referencing them (right), using their real rendered size so they never overlap.
 * Every column gets a port on each side of the table, so ELK orders the tables to limit the crossings between relationships.
 * Tables without relationships are packed in rows next to the connected ones.
 */
export async function getLayoutedNodes(
    nodes: TableNodeType[],
    edges: RelationshipEdgeType[],
): Promise<TableNodeType[]> {
    const sizes = new Map(nodes.map((node) => [node.id, tableSize(node)]));
    const connectedIds = new Set(
        edges.flatMap((edge) => [edge.source, edge.target]),
    );

    const graph: ElkNode = {
        id: 'root',
        layoutOptions: {
            'elk.algorithm': 'layered',
            'elk.direction': 'RIGHT',
            'elk.edgeRouting': 'ORTHOGONAL',
            'elk.aspectRatio': String(TARGET_ASPECT_RATIO),
            'elk.spacing.nodeNode': String(NODE_SPACING),
            'elk.spacing.componentComponent': String(COMPONENT_SPACING),
            'elk.layered.spacing.nodeNodeBetweenLayers': String(LAYER_SPACING),
            'elk.layered.layering.strategy': 'COFFMAN_GRAHAM',
            'elk.layered.coffmanGraham.layerBound': String(
                maxTablesPerLayer(
                    nodes
                        .filter((node) => connectedIds.has(node.id))
                        .map((node) => sizes.get(node.id)!),
                ),
            ),
            'elk.layered.nodePlacement.strategy': 'BRANDES_KOEPF',
            'elk.layered.nodePlacement.bk.fixedAlignment': 'BALANCED',
            'elk.layered.considerModelOrder.strategy': 'NODES_AND_EDGES',
            'elk.layered.compaction.postCompaction.strategy': 'EDGE_LENGTH',
        },
        children: nodes.map((node) => {
            const { width, height } = sizes.get(node.id)!;
            const headerHeight = Math.max(
                0,
                height - node.data.columns.length * ROW_HEIGHT,
            );

            return {
                id: node.id,
                width,
                height,
                layoutOptions: { 'elk.portConstraints': 'FIXED_POS' },
                ports: node.data.columns.flatMap((column, index) => {
                    const y = headerHeight + index * ROW_HEIGHT + ROW_HEIGHT / 2;

                    return [
                        {
                            id: portId(node.id, column.name, 'west'),
                            x: 0,
                            y,
                            layoutOptions: { 'elk.port.side': 'WEST' },
                        },
                        {
                            id: portId(node.id, column.name, 'east'),
                            x: width,
                            y,
                            layoutOptions: { 'elk.port.side': 'EAST' },
                        },
                    ];
                }),
            };
        }),
        edges: edges.map((edge) => ({
            id: edge.id,
            sources: [portId(edge.target, edge.targetHandle ?? '', 'east')],
            targets: [portId(edge.source, edge.sourceHandle ?? '', 'west')],
        })),
    };

    try {
        const layoutedGraph = await elk.layout(graph);
        const positions = new Map(
            (layoutedGraph.children ?? []).map((child) => [
                child.id,
                { x: child.x ?? 0, y: child.y ?? 0 },
            ]),
        );

        return nodes.map((node) => ({
            ...node,
            position: positions.get(node.id) ?? node.position,
        }));
    } catch (error) {
        console.error('Erreur de layout ELK', error);

        return nodes;
    }
}
