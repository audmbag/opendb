import { Background, BackgroundVariant, Controls, Handle, Position, ReactFlow, ReactFlowProvider } from '@xyflow/react';
import type { Edge, Node, NodeProps, NodeTypes } from '@xyflow/react';
import { RouteIcon, TriangleAlertIcon } from 'lucide-react';
import '@xyflow/react/dist/style.css';
import ArtifactFrame from '@/components/ai/artifacts/artifact-frame';
import { formatNumber, useConversationId } from '@/components/ai/artifacts/format';
import { useToolArtifact } from '@/hooks/use-tool-artifact';
import { cn } from '@/lib/utils';
import type { ExplainQueryToolUIPart, PlanNode } from '@/types/agent';
import { explain } from '@/wayfinder/actions/App/Http/Controllers/Chat/ToolArtifactController';

type Props = {
    part: ExplainQueryToolUIPart;
    enabled: boolean;
};

type PlanStepNode = Node<{ step: PlanNode }, 'step'>;

const NODE_WIDTH = 260;
const COLUMN_GAP = 24;
const LEVEL_HEIGHT = 130;

function StepNode({ data: { step } }: NodeProps<PlanStepNode>) {
    return (
        <div
            className={cn('w-65 rounded-md border bg-card px-3 py-2 text-xs shadow-xs', {
                'border-amber-500/60 bg-amber-500/5': step.warning,
                'border-border': !step.warning,
            })}
        >
            <Handle type="target" position={Position.Bottom} className="opacity-0" />
            <div className="truncate font-medium" title={step.label}>{step.label}</div>
            {step.detail && (
                <div className="mt-0.5 line-clamp-2 font-mono text-[0.65rem] text-muted-foreground" title={step.detail}>
                    {step.detail}
                </div>
            )}
            {(step.rows !== null || step.cost !== null) && (
                <div className="mt-1 flex gap-3 text-[0.65rem] text-muted-foreground tabular-nums">
                    {step.rows !== null && <span>~{formatNumber(step.rows)} lignes</span>}
                    {step.cost !== null && <span>coût {formatNumber(step.cost)}</span>}
                </div>
            )}
            {step.warning && (
                <div className="mt-1 flex items-center gap-1 text-[0.65rem] font-medium text-amber-600 dark:text-amber-400">
                    <TriangleAlertIcon className="size-3" />
                    {step.warning}
                </div>
            )}
            <Handle type="source" position={Position.Top} className="opacity-0" />
        </div>
    );
}

const nodeTypes: NodeTypes = { step: StepNode };

/**
 * Lay the plan out as a tree, root on top: each leaf takes the next column, each parent is centered above its children.
 */
function layoutPlan(plan: PlanNode[]): { nodes: PlanStepNode[]; edges: Edge[] } {
    const nodes: PlanStepNode[] = [];
    const edges: Edge[] = [];
    let nextColumn = 0;

    const place = (step: PlanNode, depth: number, id: string): number => {
        const childColumns = step.children.map((child, index) => {
            const childId = `${id}.${index}`;
            edges.push({ id: `${childId}->${id}`, source: childId, target: id, type: 'smoothstep', animated: true });

            return place(child, depth + 1, childId);
        });

        const column = childColumns.length > 0 ? (childColumns[0] + childColumns[childColumns.length - 1]) / 2 : nextColumn++;

        nodes.push({
            id,
            type: 'step',
            position: { x: column * (NODE_WIDTH + COLUMN_GAP), y: depth * LEVEL_HEIGHT },
            data: { step },
            draggable: false,
        });

        return column;
    };

    plan.forEach((step, index) => place(step, 0, String(index)));

    return { nodes, edges };
}

const countWarnings = (plan: PlanNode[]): number =>
    plan.reduce((total, step) => total + (step.warning ? 1 : 0) + countWarnings(step.children), 0);

export default function ExplainPreview({ part, enabled }: Props) {
    const conversationId = useConversationId();

    const { ref, data, error, processing } = useToolArtifact<PlanNode[]>(
        explain.url({ conversation: conversationId, toolCallId: part.toolCallId }),
        enabled,
    );

    const { nodes, edges } = layoutPlan(data ?? []);
    const warnings = countWarnings(data ?? []);

    return (
        <ArtifactFrame
            ref={ref}
            icon={RouteIcon}
            title="Plan d'exécution"
            description={data ? (warnings > 0 ? `${warnings} étape${warnings > 1 ? 's' : ''} coûteuse${warnings > 1 ? 's' : ''}` : 'Aucune étape coûteuse détectée') : undefined}
            data={data}
            error={error}
            processing={processing}
            skeletonClassName="h-80"
        >
            <div className="h-96">
                <ReactFlowProvider>
                    <ReactFlow
                        nodes={nodes}
                        edges={edges}
                        nodeTypes={nodeTypes}
                        nodesConnectable={false}
                        fitView
                        fitViewOptions={{ padding: 0.2 }}
                        proOptions={{ hideAttribution: true }}
                    >
                        <Background variant={BackgroundVariant.Dots} gap={16} size={1} color="var(--muted-foreground)" />
                        <Controls showInteractive={false} className="overflow-hidden rounded-md border border-border bg-card" />
                    </ReactFlow>
                </ReactFlowProvider>
            </div>
        </ArtifactFrame>
    );
}
