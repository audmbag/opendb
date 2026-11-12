import { useEffect, useState } from 'react';
import { highlightCode, TokenSpan } from '@/components/code-block';
import type { TokenizedCode } from '@/components/code-block';
import { useSyntaxTheme } from '@/hooks/use-syntax-theme';
import { uppercaseSqlKeywords } from '@/lib/sql';
import { cn } from '@/lib/utils';

type Props = {
    sql: string;
    /**
     * Only the first lines are highlighted and shown: a long script is cut, as in a preview.
     */
    maxLines?: number;
    /**
     * Written as it is, without putting the keywords in capitals: the fields of a snippet (`${table}`) are names.
     */
    raw?: boolean;
    className?: string;
};

/**
 * A few lines of SQL highlighted with the syntax theme of the application, without the frame of a code block: the
 * highlighting of the code blocks is shared, and the plain text is shown until it is ready.
 */
export default function SqlSnippet({
    sql,
    maxLines = 4,
    raw = false,
    className,
}: Props) {
    const { definition: theme } = useSyntaxTheme();
    const lines = sql.trim().split('\n');
    const shown = lines.slice(0, maxLines).join('\n');
    const code = raw ? shown : uppercaseSqlKeywords(shown);
    const cut = lines.length > maxLines;
    const key = `${theme.value}:${code}`;
    const cached = highlightCode(code, 'sql', theme);
    const [highlighted, setHighlighted] = useState<{
        key: string;
        tokens: TokenizedCode;
    } | null>(null);

    useEffect(() => {
        let cancelled = false;

        highlightCode(code, 'sql', theme, (tokens) => {
            if (!cancelled) {
                setHighlighted({ key, tokens });
            }
        });

        return () => {
            cancelled = true;
        };
    }, [code, theme, key]);

    const tokens =
        cached ?? (highlighted?.key === key ? highlighted.tokens : null);

    return (
        <pre
            className={cn(
                'm-0 overflow-hidden font-mono text-[11px] leading-4 [overflow-wrap:anywhere] whitespace-pre-wrap',
                className,
            )}
        >
            <code>
                {tokens
                    ? tokens.tokens.map((line, lineIndex) => (
                          <span key={lineIndex} className="block">
                              {line.map((token, tokenIndex) => (
                                  <TokenSpan key={tokenIndex} token={token} />
                              ))}
                              {line.length === 0 && '\n'}
                          </span>
                      ))
                    : code}
                {cut && <span className="block text-muted-foreground">…</span>}
            </code>
        </pre>
    );
}
