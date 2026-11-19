import { useReducedMotion } from 'motion/react';
import React, { useEffect, useState } from 'react';
import { cn } from '@/lib/utils';

const QUERY_SPEED = 28;
const EXCUSE_SPEED = 18;
const RESULT_PAUSE = 320;

/**
 * The text typed one character after the other from the given delay, or all at once when the motion is reduced.
 */
function useTypewriter(text: string, { speed, delay = 0, enabled = true }: { speed: number; delay?: number; enabled?: boolean }): string {
    const reducedMotion = useReducedMotion();
    const [typed, setTyped] = useState({ text: '', length: 0 });

    useEffect(() => {
        if (!enabled || reducedMotion) {
            return;
        }

        let length = 0;
        let interval: number | undefined;
        const start = window.setTimeout(() => {
            interval = window.setInterval(() => {
                length += 1;
                setTyped({ text, length });

                if (length >= text.length) {
                    window.clearInterval(interval);
                }
            }, speed);
        }, delay);

        return () => {
            window.clearTimeout(start);
            window.clearInterval(interval);
        };
    }, [text, speed, delay, enabled, reducedMotion]);

    if (!enabled) {
        return '';
    }

    if (reducedMotion) {
        return text;
    }

    return typed.text === text ? text.slice(0, typed.length) : '';
}

const Prompt = () => <span className="text-primary select-none">opendb&gt; </span>;

const Cursor = () => <span aria-hidden className="ml-px inline-block h-[1.1em] w-[0.55em] translate-y-[0.2em] animate-[blink_1.1s_steps(1)_infinite] bg-foreground/70" />;

type Props = {
    query: string;
    result: string;
    excuse: string;
    failed: boolean;
} & React.ComponentProps<'div'>;

/**
 * The console of opendb runs the query of the error: it types the query, prints what the database answered, then
 * gives an excuse as an SQL comment.
 */
export default function QueryTerminal({ query, result, excuse, failed, className, ...props }: Props) {
    const typedQuery = useTypewriter(query, { speed: QUERY_SPEED, delay: 500 });
    const queryDone = typedQuery.length === query.length;
    const [resultShown, setResultShown] = useState(false);

    useEffect(() => {
        if (!queryDone) {
            return;
        }

        const timer = window.setTimeout(() => setResultShown(true), RESULT_PAUSE);

        return () => window.clearTimeout(timer);
    }, [queryDone]);

    const typedExcuse = useTypewriter(excuse, { speed: EXCUSE_SPEED, delay: RESULT_PAUSE, enabled: resultShown });
    const excuseDone = resultShown && typedExcuse.length === excuse.length;

    return (
        <div className={cn('font-mono text-[13px] leading-relaxed', className)} {...props}>
            <p className="sr-only" aria-live="polite">
                {query} {result} {excuse}
            </p>
            <style>{'@keyframes blink { 0%, 100% { opacity: 1 } 50% { opacity: 0 } }'}</style>
            <p aria-hidden className="break-all whitespace-pre-wrap">
                <Prompt />
                {typedQuery}
                {!queryDone && <Cursor />}
            </p>
            {resultShown && <p aria-hidden className={cn('whitespace-pre-wrap', failed ? 'text-destructive' : 'text-muted-foreground')}>{result}</p>}
            {resultShown && (
                <p aria-hidden className="whitespace-pre-wrap text-muted-foreground italic">
                    {typedExcuse && `-- ${typedExcuse}`}
                    {!excuseDone && typedExcuse && <Cursor />}
                </p>
            )}
            {excuseDone && (
                <p aria-hidden>
                    <Prompt />
                    <Cursor />
                </p>
            )}
        </div>
    );
}
