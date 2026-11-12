import {
    MiniMap,
    Panel,
    ReactFlowProvider,
    useReactFlow,
    useStoreApi,
} from '@xyflow/react';
import type { EdgeTypes, NodeChange, NodeTypes } from '@xyflow/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { InfoIcon } from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import FlowEdge, {
    edgeMarker,
} from '@/components/ai/artifacts/query-plan/flow-edge';
import {
    executionOrder,
    neighbourStep,
} from '@/components/ai/artifacts/query-plan/format';
import type { StepDirection } from '@/components/ai/artifacts/query-plan/format';
import {
    layoutPlan,
    NODE_WIDTH,
    planEdges,
} from '@/components/ai/artifacts/query-plan/layout';
import type {
    PlanFlowEdge,
    PlanFlowNode,
} from '@/components/ai/artifacts/query-plan/layout';
import PlanNode from '@/components/ai/artifacts/query-plan/plan-node';
import FlowCanvas, { prefersReducedMotion } from '@/components/flow/flow-canvas';
import type { QueryPlan } from '@/types/agent';

type Props = {
    plan: QueryPlan;
    selectedId: string;
    onSelect: (id: string) => void;
    /**
     * The detail panel of the selected step, shown beside the graph, also in full screen.
     */
    detailPanel: ReactNode;
};

type Layout = {
    positions: Awaited<ReturnType<typeof layoutPlan>>;
    /**
     * Whether the positions use the heights of the rendered steps rather than estimated ones.
     */
    isMeasured: boolean;
};

type Size = { width: number; height: number };

const nodeTypes: NodeTypes = { plan: PlanNode };

const edgeTypes: EdgeTypes = { flow: FlowEdge };

const FIT_PADDING = 0.2;

/**
 * Below this zoom the steps cannot be read: the view is centered on the selected step instead.
 */
const MIN_FIT_ZOOM = 0.75;

const MINIMAP_FROM_NODES = 12;

/**
 * The margin kept around the selected step when the view follows it.
 */
const VISIBLE_MARGIN = 24;

function PlanCanvas({ plan, selectedId, onSelect, detailPanel }: Props) {
    const { t } = useLaravelReactI18n();
    const [layout, setLayout] = useState<Layout | null>(null);
    const [sizes, setSizes] = useState<Record<string, Size>>({});
    const selectedIdRef = useRef(selectedId);
    const followedIdRef = useRef(selectedId);
    const isMeasured = plan.nodes.every((node) => sizes[node.id]);
    const store = useStoreApi<PlanFlowNode, PlanFlowEdge>();
    const { getNodes, getNodesBounds, getViewport, setCenter, setViewport } =
        useReactFlow<PlanFlowNode, PlanFlowEdge>();
    const order = useMemo(() => executionOrder(plan), [plan]);

    useEffect(() => {
        selectedIdRef.current = selectedId;
    }, [selectedId]);

    useEffect(() => {
        let isCancelled = false;

        layoutPlan(plan).then((positions) => {
            if (!isCancelled) {
                setLayout({ positions, isMeasured: false });
            }
        });

        return () => {
            isCancelled = true;
        };
    }, [plan]);

    /**
     * The nodes are derived from the plan, so the sizes measured by React Flow are kept here and given back to it:
     * without them it never considers the steps measured.
     */
    const handleNodesChange = useCallback(
        (changes: NodeChange<PlanFlowNode>[]) => {
            const measured = changes.flatMap((change) =>
                change.type === 'dimensions' && change.dimensions
                    ? [[change.id, change.dimensions] as const]
                    : [],
            );

            if (measured.length > 0) {
                setSizes((current) => ({
                    ...current,
                    ...Object.fromEntries(measured),
                }));
            }
        },
        [],
    );

    /**
     * The steps are first laid out with estimated heights and rendered hidden, then laid out again with their real height.
     */
    useEffect(() => {
        if (!isMeasured || layout === null || layout.isMeasured) {
            return;
        }

        let isCancelled = false;
        const heights = Object.fromEntries(
            Object.entries(sizes).map(([id, size]) => [id, size.height]),
        );

        layoutPlan(plan, heights).then((positions) => {
            if (!isCancelled) {
                setLayout({ positions, isMeasured: true });
            }
        });

        return () => {
            isCancelled = true;
        };
    }, [isMeasured, layout, plan, sizes]);

    const navigate = useCallback(
        (id: string, direction: StepDirection) => {
            const target = neighbourStep(plan, id, direction);

            if (target === null) {
                return;
            }

            onSelect(target);
            requestAnimationFrame(() =>
                store
                    .getState()
                    .domNode?.querySelector<HTMLElement>(
                        `[data-plan-step="${CSS.escape(target)}"]`,
                    )
                    ?.focus({ preventScroll: true }),
            );
        },
        [onSelect, plan, store],
    );

    const nodes = useMemo<PlanFlowNode[]>(
        () =>
            layout === null
                ? []
                : plan.nodes.map((node) => ({
                      id: node.id,
                      type: 'plan',
                      position: {
                          x: layout.positions[node.id]?.x ?? 0,
                          y: layout.positions[node.id]?.y ?? 0,
                      },
                      measured: sizes[node.id],
                      data: {
                          node,
                          plan,
                          step: order.get(node.id) ?? 0,
                          stepCount: plan.nodes.length,
                          selected: node.id === selectedId,
                          onSelect,
                          onNavigate: navigate,
                      },
                      draggable: false,
                      selectable: false,
                      focusable: false,
                  })),
        [layout, navigate, onSelect, order, plan, selectedId, sizes],
    );

    const edges = useMemo<PlanFlowEdge[]>(
        () =>
            planEdges(plan).map((edge) => {
                const active =
                    edge.source === selectedId || edge.target === selectedId;

                return {
                    ...edge,
                    markerEnd: edgeMarker(active),
                    focusable: false,
                    data: {
                        label: edge.data?.label ?? null,
                        rows: edge.data?.rows ?? null,
                        hot: edge.data?.hot ?? false,
                        active,
                    },
                };
            }),
        [plan, selectedId],
    );

    /**
     * Fit the plan in the view without going under a readable zoom; a plan too large is centered on the selected step.
     */
    const fit = useCallback(() => {
        const { width, height } = store.getState();
        const flowNodes = getNodes();

        if (!layout?.isMeasured || flowNodes.length === 0 || width === 0) {
            return;
        }

        const bounds = getNodesBounds(flowNodes);
        const zoom = Math.max(
            MIN_FIT_ZOOM,
            Math.min(
                1,
                width / (bounds.width * (1 + FIT_PADDING)),
                height / (bounds.height * (1 + FIT_PADDING)),
            ),
        );
        let x = (width - bounds.width * zoom) / 2 - bounds.x * zoom;
        let y = (height - bounds.height * zoom) / 2 - bounds.y * zoom;
        const selected = layout.positions[selectedIdRef.current];

        if (selected && bounds.width * zoom > width - 32) {
            x = width / 2 - (selected.x + NODE_WIDTH / 2) * zoom;
        }

        if (selected && bounds.height * zoom > height - 32) {
            const centered =
                height / 2 - (selected.y + selected.height / 2) * zoom;

            y = Math.min(
                24 - bounds.y * zoom,
                Math.max(
                    height - 24 - (bounds.y + bounds.height) * zoom,
                    centered,
                ),
            );
        }

        setViewport({ x, y, zoom });
    }, [getNodes, getNodesBounds, layout, setViewport, store]);

    /**
     * Bring the selected step into view when it is chosen from the query, the keyboard or the detail panel.
     */
    useEffect(() => {
        if (!layout?.isMeasured || followedIdRef.current === selectedId) {
            return;
        }

        followedIdRef.current = selectedId;
        const position = layout.positions[selectedId];

        if (!position) {
            return;
        }

        const { width, height } = store.getState();
        const { x, y, zoom } = getViewport();
        const left = position.x * zoom + x;
        const top = position.y * zoom + y;
        const isVisible =
            left >= VISIBLE_MARGIN &&
            top >= VISIBLE_MARGIN &&
            left + NODE_WIDTH * zoom <= width - VISIBLE_MARGIN &&
            top + position.height * zoom <= height - VISIBLE_MARGIN;

        if (!isVisible) {
            setCenter(
                position.x + NODE_WIDTH / 2,
                position.y + position.height / 2,
                { zoom, duration: prefersReducedMotion() ? 0 : 300 },
            );
        }
    }, [getViewport, layout, selectedId, setCenter, store]);

    return (
        <FlowCanvas<PlanFlowNode, PlanFlowEdge>
            label={t('query_plan.title')}
            exportFileName={`plan-${plan.planId}`}
            isReady={layout?.isMeasured ?? false}
            aside={detailPanel}
            onFitView={fit}
            className="border-t"
            canvasClassName="h-135"
            asideClassName="max-h-135"
            nodes={nodes}
            edges={edges}
            onNodesChange={handleNodesChange}
            nodeTypes={nodeTypes}
            edgeTypes={edgeTypes}
            nodesDraggable={false}
            nodesConnectable={false}
            elementsSelectable={false}
            /**
             * Without a click handler, React Flow turns off the pointer events of the steps that cannot be selected.
             */
            onNodeClick={(_, node) => onSelect(node.id)}
            zoomOnDoubleClick={false}
            minZoom={0.3}
            maxZoom={1.5}
        >
            <Panel position="top-right" className="m-3!">
                <details className="group max-w-60 rounded-md border bg-card/95 text-[11px] leading-4 text-muted-foreground shadow-xs backdrop-blur-sm">
                    <summary className="flex cursor-pointer list-none items-center gap-1.5 rounded-md px-2 py-1 font-medium outline-none select-none hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring [&::-webkit-details-marker]:hidden">
                        <InfoIcon aria-hidden="true" className="size-3.5" />
                        {t('query_plan.legend.title')}
                    </summary>
                    <ul className="flex flex-col gap-1 border-t px-2.5 py-2">
                        <li>{t('query_plan.legend.flow')}</li>
                        <li>{t('query_plan.legend.numbers')}</li>
                        <li>{t('query_plan.legend.width')}</li>
                        <li>{t('query_plan.legend.hot_path')}</li>
                        <li>{t('query_plan.legend.alert')}</li>
                    </ul>
                </details>
            </Panel>
            {plan.nodes.length > MINIMAP_FROM_NODES && (
                <MiniMap
                    pannable
                    zoomable
                    className="rounded-md border bg-card"
                />
            )}
        </FlowCanvas>
    );
}

/**
 * The tree of the plan beside the detail panel of the selected step. Each plan has its own React Flow store.
 */
export default function QueryPlanFlow(props: Props) {
    return (
        <ReactFlowProvider key={props.plan.planId}>
            <PlanCanvas {...props} />
        </ReactFlowProvider>
    );
}
