import {
    ArrowDownWideNarrowIcon,
    BracketsIcon,
    CircleIcon,
    CombineIcon,
    FilterIcon,
    HashIcon,
    LayersIcon,
    ListEndIcon,
    ListIcon,
    MergeIcon,
    PackageIcon,
    Table2Icon,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { trans } from '@/lib/i18n';
import { numberFormatter } from '@/lib/intl';
import type {
    PlanAlert,
    PlanAlertSeverity,
    PlanNode,
    PlanOperation,
    QueryPlan,
} from '@/types/agent';


export const READ_OPERATIONS: PlanOperation[] = [
    'full_scan',
    'index_full_scan',
    'range_scan',
    'index_lookup',
    'unique_lookup',
];

const JOIN_OPERATIONS: PlanOperation[] = [
    'nested_loop',
    'hash_join',
    'merge_join',
];

export const ENGINE_NAMES: Record<QueryPlan['driver'], string> = {
    mysql: 'MySQL',
    mariadb: 'MariaDB',
    pgsql: 'PostgreSQL',
    sqlite: 'SQLite',
};

export const OPERATION_ICONS: Record<PlanOperation, LucideIcon> = {
    result: ListIcon,
    full_scan: Table2Icon,
    index_full_scan: Table2Icon,
    range_scan: Table2Icon,
    index_lookup: Table2Icon,
    unique_lookup: Table2Icon,
    nested_loop: MergeIcon,
    hash_join: MergeIcon,
    merge_join: MergeIcon,
    hash: HashIcon,
    group: LayersIcon,
    sort: ArrowDownWideNarrowIcon,
    distinct: FilterIcon,
    limit: ListEndIcon,
    subquery: BracketsIcon,
    materialize: PackageIcon,
    union: CombineIcon,
    other: CircleIcon,
};

/**
 * An estimated number of rows, always prefixed with « ~ ».
 */
export function formatRows(rows: number): string {
    return `~${numberFormatter({ maximumFractionDigits: 0 }).format(rows)}`;
}

export function formatCost(cost: number): string {
    return numberFormatter({ minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(cost);
}

/**
 * A share of the total cost, between 0 and 1, as a rounded percentage.
 */
export function formatShare(share: number): string {
    return numberFormatter({ style: 'percent', maximumFractionDigits: 0 }).format(share);
}

/**
 * A signed change of the cost, between -1 and 1 (« −72 % »).
 */
export function formatChange(change: number): string {
    return numberFormatter({ style: 'percent', maximumFractionDigits: 0, signDisplay: 'always' })
        .format(change)
        .replace('-', '−');
}

export const isRead = (node: PlanNode): boolean =>
    READ_OPERATIONS.includes(node.operation);

export const isJoin = (node: PlanNode): boolean =>
    JOIN_OPERATIONS.includes(node.operation);

/**
 * The most serious alert of a step, the one shown on its card.
 */
export function worstAlert(node: PlanNode): PlanAlert | undefined {
    return (
        node.alerts.find((alert) => alert.severity === 'danger') ??
        node.alerts[0]
    );
}

export function severityOf(node: PlanNode): PlanAlertSeverity | null {
    return worstAlert(node)?.severity ?? null;
}

/**
 * Whether the card of a step shows the bar of its share of the cost.
 */
export function showsCostShare(node: PlanNode, plan: QueryPlan): boolean {
    return (
        plan.hasCosts &&
        (node.costShare ?? 0) > 0 &&
        (isRead(node) || (node.selfCost ?? 0) > 0)
    );
}

/**
 * The step selected when the plan opens: the most serious alert, otherwise the most expensive step, otherwise the result.
 */
export function defaultSelectedNode(plan: QueryPlan): string {
    const withAlert =
        plan.nodes.find((node) => severityOf(node) === 'danger') ??
        plan.nodes.find((node) => severityOf(node) === 'warn');

    if (withAlert) {
        return withAlert.id;
    }

    const mostExpensive = plan.nodes
        .filter((node) => (node.selfCost ?? 0) > 0)
        .sort(
            (first, second) => (second.selfCost ?? 0) - (first.selfCost ?? 0),
        )[0];

    return (mostExpensive ?? plan.nodes[0]).id;
}

/**
 * The rows a step receives from the steps under it, when all of them estimate it.
 */
export function inputRows(node: PlanNode, plan: QueryPlan): number | null {
    const children = plan.nodes.filter((child) => child.parentId === node.id);

    if (
        children.length === 0 ||
        children.some((child) => child.estimatedRows === null)
    ) {
        return null;
    }

    return children.reduce(
        (total, child) => total + (child.estimatedRows ?? 0),
        0,
    );
}

/**
 * The figures under the subtitle of a step: rows and own cost, or the total cost for the result.
 */
export function nodeMetrics(node: PlanNode, plan: QueryPlan): string {
    const ownCost =
        plan.hasCosts && (node.selfCost ?? 0) > 0
            ? ` · ${trans('query_plan.metrics.cost', { cost: formatCost(node.selfCost ?? 0) })}`
            : '';

    if (node.operation === 'result') {
        return plan.hasCosts && plan.totals.cost !== null
            ? trans('query_plan.metrics.total_cost', { cost: formatCost(plan.totals.cost) })
            : trans('query_plan.metrics.query_result');
    }

    if (isRead(node)) {
        const rows =
            node.rowsExamined === null
                ? trans('query_plan.metrics.rows_not_estimated')
                : trans('query_plan.metrics.rows', { rows: formatRows(node.rowsExamined) });

        return (
            rows +
            (plan.hasCosts && node.selfCost !== null
                ? ` · ${trans('query_plan.metrics.cost', { cost: formatCost(node.selfCost) })}`
                : '')
        );
    }

    if (node.estimatedRows !== null) {
        return trans('query_plan.metrics.rows', { rows: formatRows(node.estimatedRows) }) + ownCost;
    }

    const received = inputRows(node, plan);

    return (
        (received === null
            ? trans('query_plan.metrics.rows_not_estimated')
            : trans('query_plan.metrics.input_rows', { rows: formatRows(received) })) + ownCost
    );
}

/**
 * The index named by the plan that the previous plan did not use: the index created between the two calls.
 */
export function createdIndex(plan: QueryPlan): string | null {
    if (!plan.previous) {
        return null;
    }

    const previousIndexes = new Set(
        plan.previous.nodes.map((node) => node.index),
    );

    return (
        plan.nodes.find(
            (node) => node.index !== null && !previousIndexes.has(node.index),
        )?.index ?? null
    );
}

/**
 * The plan of a version of the comparison: « Before » shows the previous plan with the same component.
 */
export function planVersion(
    plan: QueryPlan,
    version: 'before' | 'after',
): QueryPlan {
    if (version === 'after' || !plan.previous) {
        return plan;
    }

    const { previous } = plan;

    return {
        ...plan,
        planId: previous.planId,
        createdAt: previous.createdAt,
        totals: previous.totals,
        nodes: previous.nodes,
        classic: previous.classic,
        raw: previous.raw,
        previous: null,
        comparison: null,
    };
}

/**
 * The number of each step in the order the engine runs them: the children of a step, from left to right, before the
 * step itself, so the tables come first and the result last.
 */
export function executionOrder(plan: QueryPlan): Map<string, number> {
    const order = new Map<string, number>();
    const visit = (node: PlanNode) => {
        plan.nodes
            .filter((child) => child.parentId === node.id)
            .forEach(visit);
        order.set(node.id, order.size + 1);
    };

    plan.nodes.filter((node) => node.parentId === null).forEach(visit);

    return order;
}

/**
 * The steps sorted in execution order.
 */
export function stepsInOrder(plan: QueryPlan): PlanNode[] {
    const order = executionOrder(plan);

    return [...plan.nodes].sort(
        (first, second) =>
            (order.get(first.id) ?? 0) - (order.get(second.id) ?? 0),
    );
}

export type StepDirection = 'ArrowUp' | 'ArrowDown' | 'ArrowLeft' | 'ArrowRight';

export const STEP_DIRECTIONS: StepDirection[] = [
    'ArrowUp',
    'ArrowDown',
    'ArrowLeft',
    'ArrowRight',
];

/**
 * The step reached from another with the arrow keys, following the tree as drawn: up to the step it feeds, down to
 * its first input, left and right to the other inputs of the same step.
 */
export function neighbourStep(
    plan: QueryPlan,
    id: string,
    direction: StepDirection,
): string | null {
    const node = plan.nodes.find((candidate) => candidate.id === id);

    if (!node) {
        return null;
    }

    const childrenOf = (parentId: string | null) =>
        plan.nodes.filter((candidate) => candidate.parentId === parentId);

    switch (direction) {
        case 'ArrowUp':
            return node.parentId;
        case 'ArrowDown':
            return childrenOf(node.id)[0]?.id ?? null;
        default: {
            const siblings = childrenOf(node.parentId);
            const index = siblings.findIndex(
                (sibling) => sibling.id === node.id,
            );

            return (
                siblings[index + (direction === 'ArrowLeft' ? -1 : 1)]?.id ??
                null
            );
        }
    }
}
