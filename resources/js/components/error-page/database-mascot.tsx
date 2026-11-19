import { motion, useAnimationControls, useMotionValue, useReducedMotion, useSpring } from 'motion/react';
import type { MotionValue } from 'motion/react';
import React, { useEffect, useRef } from 'react';
import { cn } from '@/lib/utils';

/**
 * The mood of the mascot, one per kind of error: it looks for the missing page, frowns at the intruder, falls asleep
 * during the maintenance, gets dizzy when the server fails, sweats when the requests pile up.
 */
export type MascotMood = 'curious' | 'stern' | 'sleepy' | 'flustered' | 'dizzy';

const PUPIL_RANGE = 3.2;
const EYES = [
    { x: 47, y: 58 },
    { x: 73, y: 58 },
] as const;

const BODY_STROKE = 'color-mix(in oklch, var(--foreground) 70%, transparent)';
const BODY_FILL = 'color-mix(in oklch, var(--primary) 14%, var(--card))';
const LID_FILL = 'color-mix(in oklch, var(--primary) 38%, var(--card))';

type PupilProps = {
    x: number;
    y: number;
    offsetX: MotionValue<number>;
    offsetY: MotionValue<number>;
};

const Pupil = ({ x, y, offsetX, offsetY }: PupilProps) => (
    <motion.circle cx={x} cy={y} r={3.4} fill="var(--foreground)" style={{ x: offsetX, y: offsetY }} />
);

type EyesProps = {
    mood: MascotMood;
    offsetX: MotionValue<number>;
    offsetY: MotionValue<number>;
};

/**
 * Eyes that blink every few seconds and follow the pointer, or a drawing of the mood when they cannot look.
 */
const Eyes = ({ mood, offsetX, offsetY }: EyesProps) => {
    if (mood === 'sleepy') {
        return EYES.map(({ x, y }) => (
            <path key={x} d={`M${x - 6} ${y} q6 5 12 0`} fill="none" stroke="var(--foreground)" strokeWidth={2.2} strokeLinecap="round" />
        ));
    }

    if (mood === 'dizzy') {
        return EYES.map(({ x, y }) => (
            <g key={x} transform={`translate(${x} ${y})`}>
                <motion.g animate={{ rotate: 360 }} transition={{ duration: 1.6, repeat: Infinity, ease: 'linear' }}>
                    <path d="M-4.5 -4.5 l9 9 M4.5 -4.5 l-9 9" stroke="var(--foreground)" strokeWidth={2.4} strokeLinecap="round" />
                </motion.g>
            </g>
        ));
    }

    return (
        <motion.g
            animate={{ scaleY: [1, 1, 0.1, 1] }}
            transition={{ duration: 4.2, times: [0, 0.92, 0.96, 1], repeat: Infinity, ease: 'easeInOut' }}
        >
            {EYES.map(({ x, y }) => (
                <g key={x}>
                    <circle cx={x} cy={y} r={7.5} fill="var(--background)" stroke={BODY_STROKE} strokeWidth={1.5} />
                    <Pupil x={x} y={y} offsetX={offsetX} offsetY={offsetY} />
                    {mood === 'stern' && <path d={`M${x - 8.5} ${y - 1} L${x + 8.5} ${y - 1} L${x + 8.5} ${y - 9} L${x - 8.5} ${y - 9} Z`} fill={LID_FILL} />}
                </g>
            ))}
        </motion.g>
    );
};

const MOUTHS: Record<MascotMood, string> = {
    curious: 'M56 72 q4 -4 8 0 q-4 4 -8 0',
    stern: 'M53 73 h14',
    sleepy: 'M57 72 q3 3 6 0',
    flustered: 'M51 74 q3 -3 6 0 q3 3 6 0 q3 -3 6 0',
    dizzy: 'M52 75 q8 -6 16 0',
};

/**
 * What floats around the mascot: a question mark, the « z » of its sleep, the smoke of the server, a drop of sweat.
 */
const Decoration = ({ mood }: { mood: MascotMood }) => {
    switch (mood) {
        case 'curious':
            return (
                <motion.text
                    x={92}
                    y={26}
                    fontSize={20}
                    fontWeight={700}
                    fill="var(--primary)"
                    animate={{ y: [0, -4, 0], rotate: [-8, 8, -8] }}
                    transition={{ duration: 2.4, repeat: Infinity, ease: 'easeInOut' }}
                >
                    ?
                </motion.text>
            );
        case 'sleepy':
            return [0, 1, 2].map((index) => (
                <motion.text
                    key={index}
                    x={88}
                    y={34}
                    fontSize={10 + index * 3}
                    fontWeight={700}
                    fill="var(--muted-foreground)"
                    initial={{ opacity: 0 }}
                    animate={{ opacity: [0, 1, 0], x: [0, 8, 14], y: [0, -14, -26] }}
                    transition={{ duration: 3, repeat: Infinity, delay: index, ease: 'easeOut' }}
                >
                    z
                </motion.text>
            ));
        case 'dizzy':
            return [0, 1, 2].map((index) => (
                <motion.circle
                    key={index}
                    cx={50 + index * 10}
                    cy={22}
                    r={4 + index}
                    fill="var(--muted-foreground)"
                    initial={{ opacity: 0 }}
                    animate={{ opacity: [0, 0.5, 0], y: [0, -16], scale: [0.6, 1.4] }}
                    transition={{ duration: 2, repeat: Infinity, delay: index * 0.6, ease: 'easeOut' }}
                />
            ));
        case 'flustered':
            return (
                <motion.path
                    d="M90 44 q-4 6 0 8 q4 -2 0 -8"
                    fill="color-mix(in oklch, var(--primary) 55%, var(--background))"
                    animate={{ y: [0, 12], opacity: [1, 0] }}
                    transition={{ duration: 1.4, repeat: Infinity, ease: 'easeIn' }}
                />
            );
        case 'stern':
            return null;
    }
};

/**
 * Follows the pointer with the pupils, through motion values: the mascot never renders again while the pointer moves.
 */
function usePupilTracking(svgRef: React.RefObject<SVGSVGElement | null>, enabled: boolean) {
    const targetX = useMotionValue(0);
    const targetY = useMotionValue(0);
    const offsetX = useSpring(targetX, { stiffness: 260, damping: 22 });
    const offsetY = useSpring(targetY, { stiffness: 260, damping: 22 });

    useEffect(() => {
        if (!enabled) {
            return;
        }

        const follow = (event: PointerEvent): void => {
            const bounds = svgRef.current?.getBoundingClientRect();

            if (!bounds) {
                return;
            }

            const deltaX = event.clientX - (bounds.left + bounds.width / 2);
            const deltaY = event.clientY - (bounds.top + bounds.height / 2);
            const distance = Math.hypot(deltaX, deltaY) || 1;
            const reach = Math.min(distance / 120, 1) * PUPIL_RANGE;

            targetX.set((deltaX / distance) * reach);
            targetY.set((deltaY / distance) * reach);
        };

        window.addEventListener('pointermove', follow, { passive: true });

        return () => window.removeEventListener('pointermove', follow);
    }, [enabled, svgRef, targetX, targetY]);

    return { offsetX, offsetY };
}

type Props = {
    mood: MascotMood;
    label: string;
    onPoke?: () => void;
} & Omit<React.ComponentProps<'button'>, 'onClick'>;

/**
 * A small database with a face: it hops when poked, and tells the error with its mood.
 */
export default function DatabaseMascot({ mood, label, onPoke, className, ...props }: Props) {
    const svgRef = useRef<SVGSVGElement>(null);
    const reducedMotion = useReducedMotion();
    const hop = useAnimationControls();
    const { offsetX, offsetY } = usePupilTracking(svgRef, !reducedMotion && mood !== 'sleepy' && mood !== 'dizzy');

    const poke = (): void => {
        void hop.start({ y: [0, -18, 0, -5, 0], scaleY: [1, 1.06, 0.92, 1.02, 1], transition: { duration: 0.6, ease: 'easeOut' } });
        onPoke?.();
    };

    return (
        <button type="button" aria-label={label} title={label} onClick={poke} className={cn('cursor-pointer rounded-full outline-none focus-visible:ring-2 focus-visible:ring-ring', className)} {...props}>
            <motion.svg
                ref={svgRef}
                viewBox="0 0 120 120"
                className="size-full overflow-visible"
                animate={hop}
                style={{ originY: 1 }}
            >
                <motion.g
                    animate={mood === 'dizzy' ? { rotate: [-4, 4, -4] } : mood === 'sleepy' ? { scaleY: [1, 1.025, 1] } : { y: [0, -2, 0] }}
                    transition={{ duration: mood === 'dizzy' ? 0.9 : 3, repeat: Infinity, ease: 'easeInOut' }}
                    style={{ originY: 1 }}
                >
                    <ellipse cx={60} cy={110} rx={30} ry={4} fill="color-mix(in oklch, var(--foreground) 10%, transparent)" />
                    <path d="M28 36 v60 a32 11 0 0 0 64 0 v-60" fill={BODY_FILL} stroke={BODY_STROKE} strokeWidth={2} />
                    <path d="M28 78 a32 11 0 0 0 64 0" fill="none" stroke={BODY_STROKE} strokeWidth={1.5} opacity={0.35} />
                    <ellipse cx={60} cy={36} rx={32} ry={11} fill={LID_FILL} stroke={BODY_STROKE} strokeWidth={2} />
                    <Eyes mood={mood} offsetX={offsetX} offsetY={offsetY} />
                    <path d={MOUTHS[mood]} fill={mood === 'curious' ? 'var(--foreground)' : 'none'} stroke="var(--foreground)" strokeWidth={2} strokeLinecap="round" strokeLinejoin="round" />
                    {(mood === 'flustered' || mood === 'curious') && (
                        <g fill="color-mix(in oklch, var(--destructive) 35%, transparent)">
                            <ellipse cx={38} cy={67} rx={4} ry={2.2} />
                            <ellipse cx={82} cy={67} rx={4} ry={2.2} />
                        </g>
                    )}
                    <Decoration mood={mood} />
                </motion.g>
            </motion.svg>
        </button>
    );
}
