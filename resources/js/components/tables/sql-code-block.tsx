import { useEffect, useState } from 'react';
import type { ComponentProps } from 'react';
import { CodeBlock, highlightCode } from '@/components/code-block';
import { useSyntaxTheme } from '@/hooks/use-syntax-theme';
import { uppercaseSqlKeywords } from '@/lib/sql';

type Props = Omit<ComponentProps<typeof CodeBlock>, 'language'>;

/**
 * A block of SQL highlighted from its first display, its keywords in capitals. CodeBlock reads its highlighted tokens once, when it is drawn:
 * when the highlighting finishes after that, the block stays plain. It is drawn again, from the cache, once the
 * highlighting is ready, whether it ends before or after this component subscribes to it.
 */
export default function SqlCodeBlock({ code: rawCode, ...props }: Props) {
    // Whether the tokens are cached changes outside of React: it is read again at each render.
    'use no memo';

    const code = uppercaseSqlKeywords(rawCode);
    const { definition: theme } = useSyntaxTheme();
    const highlightKey = `${theme.value}:${code}`;

    const [highlighted, setHighlighted] = useState<string | null>(null);
    const cached = highlightCode(code, 'sql', theme) !== null;

    useEffect(() => {
        let cancelled = false;
        const markHighlighted = () =>
            !cancelled && setHighlighted(highlightKey);

        // Already cached when the subscription would come too late: no callback will follow.
        if (highlightCode(code, 'sql', theme, markHighlighted)) {
            queueMicrotask(markHighlighted);
        }

        return () => {
            cancelled = true;
        };
    }, [code, theme, highlightKey]);

    return (
        <CodeBlock
            key={
                cached || highlighted === highlightKey
                    ? `highlighted:${theme.value}`
                    : 'plain'
            }
            code={code}
            language="sql"
            {...props}
        />
    );
}
