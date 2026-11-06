import {
    BaseEdge,
    EdgeLabelRenderer,
    getSmoothStepPath,
    Position,
} from '@xyflow/react';
import type { EdgeProps, EdgeTypes } from '@xyflow/react';
import { useMemo } from 'react';
import { cn } from '@/lib/utils';
import type { Point, RelationshipEdgeType } from './types';

function pathLength(points: Point[]): number {
    return points.reduce(
        (total, point, index) =>
            index === 0
                ? total
                : total +
                  Math.hypot(
                      point.x - points[index - 1].x,
                      point.y - points[index - 1].y,
                  ),
        0,
    );
}

function pointAlongPath(points: Point[], distance: number): Point {
    if (points.length === 0) {
        return { x: 0, y: 0 };
    }

    let remaining = distance;

    for (let index = 1; index < points.length; index += 1) {
        const from = points[index - 1];
        const to = points[index];
        const segmentLength = Math.hypot(to.x - from.x, to.y - from.y);

        if (remaining <= segmentLength || index === points.length - 1) {
            const ratio = segmentLength ? remaining / segmentLength : 0;

            return {
                x: from.x + (to.x - from.x) * ratio,
                y: from.y + (to.y - from.y) * ratio,
            };
        }

        remaining -= segmentLength;
    }

    return points[points.length - 1];
}

function roundedPathFromPoints(points: Point[], radius = 8): string {
    if (points.length < 2) {
        return '';
    }

    if (points.length === 2) {
        return `M ${points[0].x},${points[0].y} L ${points[1].x},${points[1].y}`;
    }

    let path = `M ${points[0].x},${points[0].y}`;

    for (let index = 1; index < points.length - 1; index += 1) {
        const previous = points[index - 1];
        const current = points[index];
        const next = points[index + 1];
        const toPrevious = {
            x: previous.x - current.x,
            y: previous.y - current.y,
        };
        const toNext = { x: next.x - current.x, y: next.y - current.y };
        const previousLength = Math.hypot(toPrevious.x, toPrevious.y) || 1;
        const nextLength = Math.hypot(toNext.x, toNext.y) || 1;
        const bendRadius = Math.min(radius, previousLength / 2, nextLength / 2);
        const before = {
            x: current.x + (toPrevious.x / previousLength) * bendRadius,
            y: current.y + (toPrevious.y / previousLength) * bendRadius,
        };
        const after = {
            x: current.x + (toNext.x / nextLength) * bendRadius,
            y: current.y + (toNext.y / nextLength) * bendRadius,
        };

        path += ` L ${before.x},${before.y} Q ${current.x},${current.y} ${after.x},${after.y}`;
    }

    const last = points[points.length - 1];

    return `${path} L ${last.x},${last.y}`;
}

export function RelationshipEdge({
    id,
    sourceX,
    sourceY,
    targetX,
    targetY,
    sourcePosition = Position.Right,
    targetPosition = Position.Left,
    data,
    style,
}: EdgeProps<RelationshipEdgeType>) {
    const isActive = data?.active ?? false;
    const points = useMemo<Point[]>(
        () => [
            { x: sourceX, y: sourceY },
            ...(data?.bendPoints ?? []),
            { x: targetX, y: targetY },
        ],
        [data?.bendPoints, sourceX, sourceY, targetX, targetY],
    );
    const edgePath = useMemo(() => {
        if (data?.bendPoints) {
            return roundedPathFromPoints(points);
        }

        const [fallbackPath] = getSmoothStepPath({
            sourceX,
            sourceY,
            sourcePosition,
            targetX,
            targetY,
            targetPosition,
            borderRadius: 8,
        });

        return fallbackPath;
    }, [
        data?.bendPoints,
        points,
        sourceX,
        sourceY,
        sourcePosition,
        targetX,
        targetY,
        targetPosition,
    ]);
    const middlePoint = pointAlongPath(points, pathLength(points) / 2);

    return (
        <>
            <BaseEdge
                id={id}
                path={edgePath}
                style={{
                    ...style,
                    strokeWidth: isActive ? 2 : 1.5,
                    ...(isActive && { strokeDasharray: '6 4' }),
                }}
                className={cn(
                    isActive
                        ? 'flow-edge-animated stroke-primary'
                        : 'stroke-muted-foreground',
                )}
            />
            <EdgeLabelRenderer>
                {[
                    { point: { x: sourceX, y: sourceY }, target: false },
                    { point: { x: targetX, y: targetY }, target: true },
                ].map(({ point, target }, index) => (
                    <div
                        key={index}
                        style={{
                            position: 'absolute',
                            transform: `translate(-50%, -50%) translate(${point.x}px, ${point.y}px)`,
                            pointerEvents: 'none',
                        }}
                        className={cn(
                            'h-2.5 w-2.5 border-2 border-muted-foreground bg-background',
                            target && 'bg-muted-foreground',
                            isActive && 'border-primary',
                            isActive && target && 'bg-primary',
                        )}
                    />
                ))}
                {data?.showForeignKeys && data.sourceColumn && (
                    <div
                        style={{
                            position: 'absolute',
                            transform: `translate(-50%, -50%) translate(${middlePoint.x}px, ${middlePoint.y - 12}px)`,
                            pointerEvents: 'none',
                        }}
                        className={cn(
                            'rounded-r-sm bg-background/90 px-1 text-[9px] text-foreground',
                            isActive && 'text-primary',
                        )}
                    >
                        {data.sourceColumn}
                    </div>
                )}
            </EdgeLabelRenderer>
        </>
    );
}

export const edgeTypes: EdgeTypes = { relationship: RelationshipEdge };
