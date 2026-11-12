import {
    BaseEdge,
    EdgeLabelRenderer,
    getSmoothStepPath,
    MarkerType,
} from '@xyflow/react';
import type { EdgeMarker, EdgeProps } from '@xyflow/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { formatRows } from '@/components/ai/artifacts/query-plan/format';
import type { PlanFlowEdge } from '@/components/ai/artifacts/query-plan/layout';
import { translateMessage } from '@/lib/i18n';
import { cn } from '@/lib/utils';

/**
 * The thickness of an edge grows with the rows it carries, between 1.5 and 6 pixels.
 */
const strokeWidth = (rows: number | null): number =>
    Math.min(6, Math.max(1.5, 1.5 + Math.log10((rows ?? 0) + 1) * 0.8));

const strokeColor = (active: boolean): string =>
    active ? 'var(--primary)' : 'var(--muted-foreground)';

/**
 * The arrow at the end of an edge, in the color of the edge.
 */
export const edgeMarker = (active: boolean): EdgeMarker => ({
    type: MarkerType.ArrowClosed,
    width: 14,
    height: 14,
    markerUnits: 'userSpaceOnUse',
    color: strokeColor(active),
});

/**
 * The rows sent by a step to the one it feeds. The edges of the selected step are drawn in the primary color, and the
 * most expensive path is fully opaque with dashes moving towards the result.
 */
export default function FlowEdge({
    id,
    sourceX,
    sourceY,
    targetX,
    targetY,
    sourcePosition,
    targetPosition,
    markerEnd,
    data,
}: EdgeProps<PlanFlowEdge>) {
    const { t } = useLaravelReactI18n();
    const [path, labelX, labelY] = getSmoothStepPath({
        sourceX,
        sourceY,
        targetX,
        targetY,
        sourcePosition,
        targetPosition,
        borderRadius: 12,
    });
    const active = data?.active ?? false;
    const hot = data?.hot ?? false;
    const width = strokeWidth(data?.rows ?? null);
    const label =
        data?.rows != null
            ? t('query_plan.metrics.rows', { rows: formatRows(data.rows) })
            : translateMessage(t, data?.label);

    return (
        <>
            <BaseEdge
                id={id}
                path={path}
                markerEnd={markerEnd}
                style={{
                    strokeWidth: width,
                    stroke: strokeColor(active),
                    opacity: active || hot ? 1 : 0.45,
                    transition: 'stroke 120ms, opacity 120ms',
                }}
            />
            {hot && (
                <path
                    d={path}
                    fill="none"
                    aria-hidden="true"
                    className="flow-edge-animated pointer-events-none"
                    style={{
                        stroke: 'var(--background)',
                        strokeWidth: Math.max(1, width - 1),
                        strokeDasharray: '2 8',
                        strokeLinecap: 'round',
                        opacity: 0.8,
                    }}
                />
            )}
            {label && (
                <EdgeLabelRenderer>
                    <span
                        aria-hidden="true"
                        className={cn(
                            'nodrag nopan pointer-events-none absolute inline-flex h-5.5 items-center rounded-full border bg-card px-2 text-[11px] whitespace-nowrap text-muted-foreground tabular-nums shadow-xs transition-colors',
                            active && 'border-primary/60 text-foreground',
                        )}
                        style={{
                            transform: `translate(-50%, -50%) translate(${labelX}px, ${labelY}px)`,
                        }}
                    >
                        {label}
                    </span>
                </EdgeLabelRenderer>
            )}
        </>
    );
}
