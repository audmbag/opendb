import { motion, useAnimationControls } from 'motion/react';
import React, { memo, useEffect } from 'react';
import { cn } from '@/lib/utils';

/**
 * The digits of the error codes, drawn in the same « ANSI Shadow » letters as the logo of opendb.
 */
const DIGITS: Record<string, string[]> = {
    '0': [' ██████╗ ', '██╔═████╗', '██║██╔██║', '████╔╝██║', '╚██████╔╝', ' ╚═════╝ '],
    '1': [' ██╗', '███║', '╚██║', ' ██║', ' ██║', ' ╚═╝'],
    '2': ['██████╗ ', '╚════██╗', ' █████╔╝', '██╔═══╝ ', '███████╗', '╚══════╝'],
    '3': ['██████╗ ', '╚════██╗', ' █████╔╝', ' ╚═══██╗', '██████╔╝', '╚═════╝ '],
    '4': ['██╗  ██╗', '██║  ██║', '███████║', '╚════██║', '     ██║', '     ╚═╝'],
    '5': ['███████╗', '██╔════╝', '███████╗', '╚════██║', '███████║', '╚══════╝'],
    '9': [' █████╗ ', '██╔══██╗', '╚██████║', ' ╚═══██║', ' █████╔╝', ' ╚════╝ '],
};

const LINE_COUNT = 6;

/**
 * Like the logo, each line goes from the muted foreground to the primary color of the current theme.
 */
const lineColors = Array.from(
    { length: LINE_COUNT },
    (_, index) => `color-mix(in oklch, var(--primary) ${Math.round((index / (LINE_COUNT - 1)) * 100)}%, var(--muted-foreground))`,
);

type DigitProps = {
    digit: string;
    index: number;
};

/**
 * A digit falls into place, jumps when hovered, and does a somersault when clicked.
 */
const Digit = ({ digit, index }: DigitProps) => {
    const controls = useAnimationControls();

    useEffect(() => {
        void controls.start({ y: 0, opacity: 1, transition: { type: 'spring', bounce: 0.55, duration: 0.8, delay: 0.1 + index * 0.12 } });
    }, [controls, index]);

    return (
        <motion.button
            type="button"
            tabIndex={-1}
            aria-hidden
            className="cursor-pointer select-none"
            initial={{ y: -40, opacity: 0 }}
            animate={controls}
            whileHover={{ y: -6, transition: { type: 'spring', bounce: 0.6, duration: 0.4 } }}
            onClick={() => controls.start({ rotateX: [0, 360], transition: { duration: 0.7, ease: [0.4, 0, 0.2, 1] } })}
            style={{ transformPerspective: 600 }}
        >
            {DIGITS[digit].map((line, lineIndex) => (
                <pre key={lineIndex} className="leading-[1.18]" style={{ color: lineColors[lineIndex] }}>
                    {line}
                </pre>
            ))}
        </motion.button>
    );
};

type Props = {
    status: number;
} & React.ComponentProps<'div'>;

/**
 * The error code in big letters: the digits without a drawing are left out, the code stays readable in the title.
 */
const AsciiStatus = memo(({ status, className, ...props }: Props) => {
    const digits = String(status).split('').filter((digit) => digit in DIGITS);

    return (
        <div aria-hidden className={cn('flex gap-[1ch] text-[10px] sm:text-sm', className)} {...props}>
            {digits.map((digit, index) => (
                <Digit key={index} digit={digit} index={index} />
            ))}
        </div>
    );
});

export default AsciiStatus;
