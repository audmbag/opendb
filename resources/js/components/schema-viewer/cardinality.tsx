import { Position } from '@xyflow/react';
import { cn } from '@/lib/utils';

type Props = {
    x: number;
    y: number;
    position: Position;
    /**
     * The most rows on this side: one, or many drawn as a crow's foot.
     */
    most: 'one' | 'many';
    /**
     * Whether this side may have no row at all, drawn as a circle instead of a bar.
     */
    optional: boolean;
    isActive: boolean;
};

const HALF_HEIGHT = 6;

/**
 * The end of a relationship in crow's foot notation, against the side of the table it touches: the most rows next to
 * the table, the fewest further along the line.
 */
export default function Cardinality({
    x,
    y,
    position,
    most,
    optional,
    isActive,
}: Props) {
    const direction = position === Position.Left ? -1 : 1;
    const at = (distance: number) => x + direction * distance;
    const bar = (distance: number) =>
        `M ${at(distance)},${y - HALF_HEIGHT} L ${at(distance)},${y + HALF_HEIGHT}`;
    const nearest =
        most === 'many'
            ? `M ${x},${y - HALF_HEIGHT} L ${at(12)},${y} L ${x},${y + HALF_HEIGHT} M ${x},${y} L ${at(12)},${y}`
            : bar(6);
    const fewestDistance = most === 'many' ? 17 : 12;

    return (
        <g
            className={cn(
                'pointer-events-none fill-none',
                isActive ? 'stroke-primary' : 'stroke-muted-foreground',
            )}
            strokeWidth={1.5}
        >
            <path d={nearest} />
            {optional ? (
                <circle
                    cx={at(fewestDistance + 1)}
                    cy={y}
                    r={3.5}
                    className="fill-background"
                />
            ) : (
                <path d={bar(fewestDistance - 2)} />
            )}
        </g>
    );
}
