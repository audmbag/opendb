import type { Edge, Node } from '@xyflow/react';
import { showsCostShare } from '@/components/ai/artifacts/query-plan/format';
import type { StepDirection } from '@/components/ai/artifacts/query-plan/format';
import { layoutEngine } from '@/lib/elk';
import type { ServerMessage } from '@/lib/i18n';
import type { PlanNode, QueryPlan } from '@/types/agent';

export const NODE_WIDTH = 264;

const NODE_SPACING = 48;
const RANK_SPACING = 88;

export type PlanNodeData = {
    node: PlanNode;
    plan: QueryPlan;
    /**
     * The number of the step in execution order, and the number of steps.
     */
    step: number;
    stepCount: number;
    selected: boolean;
    onSelect: (id: string) => void;
    onNavigate: (id: string, direction: StepDirection) => void;
};

export type PlanFlowNode = Node<PlanNodeData, 'plan'>;

export type FlowEdgeData = {
    label: ServerMessage | null;
    rows: number | null;
    /**
     * Whether the edge is on the most expensive path, from a table to the result.
     */
    hot: boolean;
    /**
     * Whether the edge enters or leaves the selected step.
     */
    active?: boolean;
};

export type PlanFlowEdge = Edge<FlowEdgeData, 'flow'>;

/**
 * The height of a card before it is measured: one more line for the bar of the cost, one more for the alert.
 */
export function estimatedNodeHeight(node: PlanNode, plan: QueryPlan): number {
    return (
        64 +
        18 +
        (showsCostShare(node, plan) ? 22 : 0) +
        (node.alerts.length > 0 ? 26 : 0)
    );
}

/**
 * The steps on the most expensive path: from the result, each time down to the child with the highest cumulated cost.
 */
export function hottestPath(plan: QueryPlan): Set<string> {
    const hot = new Set<string>();
    let current = plan.nodes.find((node) => node.parentId === null);

    while (current) {
        const parentId: string = current.id;
        const children = plan.nodes.filter(
            (node) => node.parentId === parentId,
        );

        current = children.sort(
            (first, second) => (second.cost ?? 0) - (first.cost ?? 0),
        )[0];

        if (current) {
            hot.add(current.id);
        }
    }

    return hot;
}

/**
 * The positions of the steps, tables at the bottom and result at the top, the children of a step from left to right
 * in execution order. Heights measured after a first render replace the estimated ones.
 */
export async function layoutPlan(
    plan: QueryPlan,
    measuredHeights: Record<string, number> = {},
): Promise<Record<string, { x: number; y: number; height: number }>> {
    const heightOf = (node: PlanNode) =>
        measuredHeights[node.id] ?? estimatedNodeHeight(node, plan);

    const graph = await layoutEngine().layout({
        id: 'plan',
        layoutOptions: {
            'elk.algorithm': 'layered',
            'elk.direction': 'UP',
            'elk.spacing.nodeNode': String(NODE_SPACING),
            'elk.layered.spacing.nodeNodeBetweenLayers': String(RANK_SPACING),
            'elk.layered.considerModelOrder.strategy': 'NODES_AND_EDGES',
            'elk.layered.crossingMinimization.forceNodeModelOrder': 'true',
            'elk.layered.nodePlacement.strategy': 'BRANDES_KOEPF',
            'elk.edgeRouting': 'ORTHOGONAL',
        },
        children: plan.nodes.map((node) => ({
            id: node.id,
            width: NODE_WIDTH,
            height: heightOf(node),
        })),
        edges: plan.nodes
            .filter((node) => node.parentId !== null)
            .map((node) => ({
                id: `${node.id}->${node.parentId}`,
                sources: [node.id],
                targets: [node.parentId as string],
            })),
    });

    return Object.fromEntries(
        (graph.children ?? []).map((child) => [
            child.id,
            { x: child.x ?? 0, y: child.y ?? 0, height: child.height ?? 0 },
        ]),
    );
}

/**
 * The edges of the plan, from each step to the one it feeds, labelled with the rows it sends.
 */
export function planEdges(plan: QueryPlan): PlanFlowEdge[] {
    const hot = hottestPath(plan);

    return plan.nodes
        .filter((node) => node.parentId !== null)
        .map((node) => ({
            id: `${node.id}->${node.parentId}`,
            source: node.id,
            target: node.parentId as string,
            type: 'flow',
            data: {
                label: node.estimatedRows === null ? node.outputLabel : null,
                rows: node.estimatedRows,
                hot: hot.has(node.id),
            },
        }));
}
