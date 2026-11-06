import { useEffect, useState } from 'react';
import type { CSSProperties } from 'react';
import type { ThemedToken } from 'shiki';
import {
    executionOrder,
    isJoin,
    isRead,
} from '@/components/ai/artifacts/query-plan/format';
import { highlightCode } from '@/components/code-block';
import { useSyntaxTheme } from '@/hooks/use-syntax-theme';
import { uppercaseSqlKeywords } from '@/lib/sql';
import { cn } from '@/lib/utils';
import type { CellValue, PlanNode, QueryPlan } from '@/types/agent';

type Props = {
    query: string;
    bindings: Record<string, CellValue>;
    /**
     * The plan whose steps the lines of the query lead to.
     */
    plan: QueryPlan;
    /**
     * The selected step, whose lines of the query are highlighted.
     */
    selectedId: string | null;
    onSelect: (id: string) => void;
};

const CLAUSES = [
    'FROM',
    'LEFT JOIN',
    'RIGHT JOIN',
    'INNER JOIN',
    'FULL JOIN',
    'CROSS JOIN',
    'JOIN',
    'WHERE',
    'GROUP BY',
    'HAVING',
    'ORDER BY',
    'LIMIT',
    'UNION',
];

/**
 * The query broken into one line per clause, outside of string literals: before FROM, JOIN, WHERE, GROUP BY…
 */
export function sqlLines(query: string): string[] {
    const parts = query
        .replace(/\s+/g, ' ')
        .trim()
        .split(/('(?:[^'\\]|\\.)*')/);
    const clauses = new RegExp(
        `\\s(?<!(?:LEFT|RIGHT|INNER|FULL|CROSS) )(${CLAUSES.map((clause) => clause.replace(' ', '\\s')).join('|')})\\s`,
        'gi',
    );

    return parts
        .map((part, index) =>
            index % 2 === 1
                ? part
                : part.replace(
                      clauses,
                      (_, clause: string) => `\n${clause.toUpperCase()} `,
                  ),
        )
        .join('')
        .split('\n')
        .map((line) => line.trim())
        .filter(Boolean);
}

const tablePattern = (table: string) =>
    new RegExp(`\\b${table.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}\\b`, 'i');

/**
 * Whether a line of the query belongs to a step: the FROM or JOIN of its table and the WHERE for a read, GROUP BY for
 * a group, the SELECT for the result…
 */
export function lineBelongsTo(
    line: string,
    node: PlanNode | null | undefined,
): boolean {
    if (!node) {
        return false;
    }

    const upper = line.toUpperCase();
    const table = node.table ? tablePattern(node.table) : null;

    switch (node.operation) {
        case 'result':
            return upper.startsWith('SELECT') || upper.startsWith('WITH');
        case 'group':
            return upper.startsWith('GROUP BY');
        case 'sort':
            return upper.startsWith('ORDER BY');
        case 'limit':
            return upper.startsWith('LIMIT');
        case 'distinct':
            return upper.startsWith('SELECT DISTINCT');
        case 'hash':
            return table !== null && /\bJOIN\b/.test(upper) && table.test(line);
        default:
            if (isJoin(node)) {
                return /\bJOIN\b/.test(upper);
            }

            if (isRead(node)) {
                return (
                    ((upper.startsWith('FROM') || /\bJOIN\b/.test(upper)) &&
                        table !== null &&
                        table.test(line)) ||
                    (upper.startsWith('WHERE') && node.condition !== null)
                );
            }

            return false;
    }
}

/**
 * The steps a line of the query leads to, the reads of a table first since they are the most specific, then in
 * execution order.
 */
export function stepsOfLine(
    line: string,
    plan: QueryPlan,
    order: Map<string, number>,
): PlanNode[] {
    return plan.nodes
        .filter((node) => lineBelongsTo(line, node))
        .sort(
            (first, second) =>
                Number(isRead(second)) - Number(isRead(first)) ||
                (order.get(first.id) ?? 0) - (order.get(second.id) ?? 0),
        );
}

/**
 * A token highlighted by Shiki, rendered like in the code blocks of the conversation: the light theme in its style, the
 * dark theme in the --shiki-dark variable. Its font style is a bit field: 1 italic, 2 bold, 4 underline.
 */
function ShikiToken({ token }: { token: ThemedToken }) {
    const fontStyle = token.fontStyle ?? 0;

    return (
        <span
            className="dark:bg-(--shiki-dark-bg)! dark:text-(--shiki-dark)!"
            style={
                {
                    backgroundColor: token.bgColor,
                    color: token.color,
                    fontStyle: fontStyle & 1 ? 'italic' : undefined,
                    fontWeight: fontStyle & 2 ? 'bold' : undefined,
                    textDecoration: fontStyle & 4 ? 'underline' : undefined,
                    ...token.htmlStyle,
                } as CSSProperties
            }
        >
            {token.content}
        </span>
    );
}

/**
 * The tokens of each line of the query, highlighted by Shiki like the code blocks of the conversation, or null while
 * its SQL grammar loads.
 */
function useSqlTokens(code: string): ThemedToken[][] | null {
    const { definition: theme } = useSyntaxTheme();
    const highlightKey = `${theme.value}:${code}`;
    const [highlighted, setHighlighted] = useState<{
        key: string;
        tokens: ThemedToken[][];
    } | null>(null);
    const cached = highlightCode(code, 'sql', theme);

    useEffect(() => {
        let isCancelled = false;

        highlightCode(code, 'sql', theme, (result) => {
            if (!isCancelled) {
                setHighlighted({ key: highlightKey, tokens: result.tokens });
            }
        });

        return () => {
            isCancelled = true;
        };
    }, [code, theme, highlightKey]);

    return (
        cached?.tokens ??
        (highlighted?.key === highlightKey ? highlighted.tokens : null)
    );
}

const formatBinding = (value: CellValue): string =>
    typeof value === 'string' ? `'${value}'` : String(value);

/**
 * The analysed query, one clause per line. A line that belongs to steps of the plan is a button that selects them in
 * turn; the lines of the selected step are highlighted and carry the numbers of their steps.
 */
export default function QueryPlanSql({
    query,
    bindings,
    plan,
    selectedId,
    onSelect,
}: Props) {
    const parameters = Object.entries(bindings);
    const order = executionOrder(plan);
    const lines = sqlLines(uppercaseSqlKeywords(query));
    const tokens = useSqlTokens(lines.join('\n'));

    return (
        <div className="mx-4 mb-3 overflow-hidden rounded-lg border">
            <div className="flex min-h-8.5 flex-wrap items-center justify-between gap-2 border-b bg-muted/40 px-3 text-xs text-muted-foreground">
                <span>Requête analysée</span>
                {parameters.length > 0 && (
                    <span className="inline-flex flex-wrap items-center gap-1.5 py-1">
                        {parameters.length > 1 ? 'Paramètres' : 'Paramètre'}
                        {parameters.map(([name, value]) => (
                            <span
                                key={name}
                                className="inline-flex h-5 items-center rounded bg-muted px-1.5 font-mono text-foreground"
                            >
                                :{name} = {formatBinding(value)}
                            </span>
                        ))}
                    </span>
                )}
            </div>
            <div className="overflow-x-auto py-1.5">
                <ol
                    aria-label="Lignes de la requête"
                    className="w-max min-w-full font-mono text-[12.5px] leading-[1.7]"
                >
                    {lines.map((line, index) => {
                        const steps = stepsOfLine(line, plan, order);
                        const position = steps.findIndex(
                            (step) => step.id === selectedId,
                        );
                        const isActive = position !== -1;
                        const content = (
                            <>
                                <span
                                    aria-hidden="true"
                                    className="w-6 shrink-0 pr-3 text-right text-muted-foreground/60 tabular-nums select-none"
                                >
                                    {index + 1}
                                </span>
                                <span className="flex-1 whitespace-pre">
                                    {tokens?.[index]
                                        ? tokens[index].map(
                                              (token, tokenIndex) => (
                                                  <ShikiToken
                                                      key={tokenIndex}
                                                      token={token}
                                                  />
                                              ),
                                          )
                                        : line}
                                </span>
                                {steps.length > 0 && (
                                    <span
                                        aria-hidden="true"
                                        className="ml-4 flex shrink-0 gap-1 font-sans"
                                    >
                                        {steps.map((step) => (
                                            <span
                                                key={step.id}
                                                title={step.label}
                                                className={cn(
                                                    'flex size-4.5 items-center justify-center rounded-full bg-muted text-[10px] font-semibold text-muted-foreground tabular-nums transition-colors',
                                                    step.id === selectedId &&
                                                        'bg-primary text-primary-foreground',
                                                )}
                                            >
                                                {order.get(step.id)}
                                            </span>
                                        ))}
                                    </span>
                                )}
                            </>
                        );
                        const lineClassName = cn(
                            'relative flex w-full items-center px-3 text-left transition-colors duration-120',
                            isActive &&
                                'bg-primary/10 before:absolute before:inset-y-0 before:left-0 before:w-0.5 before:bg-primary',
                        );

                        return (
                            <li key={index}>
                                {steps.length > 0 ? (
                                    <button
                                        type="button"
                                        onClick={() =>
                                            onSelect(
                                                steps[
                                                    (position + 1) %
                                                        steps.length
                                                ].id,
                                            )
                                        }
                                        aria-pressed={isActive}
                                        aria-label={`Ligne ${index + 1} : ${line}. Voir l'étape ${steps
                                            .map(
                                                (step) =>
                                                    `${order.get(step.id)} (${step.label})`,
                                            )
                                            .join(', ')}`}
                                        className={cn(
                                            lineClassName,
                                            'cursor-pointer outline-none hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-inset',
                                            isActive && 'hover:bg-primary/15',
                                        )}
                                    >
                                        {content}
                                    </button>
                                ) : (
                                    <div
                                        className={cn(
                                            lineClassName,
                                            'text-muted-foreground',
                                        )}
                                    >
                                        {content}
                                    </div>
                                )}
                            </li>
                        );
                    })}
                </ol>
            </div>
        </div>
    );
}
