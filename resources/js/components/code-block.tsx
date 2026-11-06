import { CheckIcon, CopyIcon } from 'lucide-react';
import type { ComponentProps, CSSProperties, HTMLAttributes } from 'react';
import {
    createContext,
    memo,
    useCallback,
    useContext,
    useEffect,
    useMemo,
    useRef,
    useState,
} from 'react';
import type {
    BundledLanguage,
    BundledTheme,
    HighlighterGeneric,
    ThemedToken,
} from 'shiki';
import { createHighlighter } from 'shiki';
import { Button } from '@/components/ui/button';
import { useSyntaxTheme } from '@/hooks/use-syntax-theme';
import type { SyntaxThemeDefinition } from '@/hooks/use-syntax-theme';
import { cn } from '@/lib/utils';

/*
 * A local copy of the code block of AI Elements (components/ai-elements/code-block.tsx), whose Shiki themes are fixed:
 * this one colors the code with the syntax theme chosen in the settings.
 */

type KeyedToken = {
    token: ThemedToken;
    key: string;
};

type KeyedLine = {
    tokens: KeyedToken[];
    key: string;
};

export type TokenizedCode = {
    tokens: ThemedToken[][];
    fg: string;
    bg: string;
};

type CodeBlockProps = HTMLAttributes<HTMLDivElement> & {
    code: string;
    language: BundledLanguage;
    showLineNumbers?: boolean;
};

/**
 * Shiki keeps the font style of a token in a bit field: 1 italic, 2 bold, 4 underline.
 */
const hasFontStyle = (fontStyle: number | undefined, flag: number): boolean =>
    ((fontStyle ?? 0) & flag) !== 0;

const addKeysToTokens = (lines: ThemedToken[][]): KeyedLine[] =>
    lines.map((line, lineIndex) => ({
        key: `line-${lineIndex}`,
        tokens: line.map((token, tokenIndex) => ({
            key: `line-${lineIndex}-${tokenIndex}`,
            token,
        })),
    }));

/**
 * A highlighted token: the light theme in its style, the dark theme in the --shiki-dark variables.
 */
export function TokenSpan({ token }: { token: ThemedToken }) {
    return (
        <span
            className="dark:bg-(--shiki-dark-bg)! dark:text-(--shiki-dark)!"
            style={
                {
                    backgroundColor: token.bgColor,
                    color: token.color,
                    fontStyle: hasFontStyle(token.fontStyle, 1)
                        ? 'italic'
                        : undefined,
                    fontWeight: hasFontStyle(token.fontStyle, 2)
                        ? 'bold'
                        : undefined,
                    textDecoration: hasFontStyle(token.fontStyle, 4)
                        ? 'underline'
                        : undefined,
                    ...token.htmlStyle,
                } as CSSProperties
            }
        >
            {token.content}
        </span>
    );
}

const LINE_NUMBER_CLASSES = cn(
    'block',
    'before:content-[counter(line)]',
    'before:inline-block',
    'before:[counter-increment:line]',
    'before:w-8',
    'before:mr-4',
    'before:text-right',
    'before:text-muted-foreground/50',
    'before:font-mono',
    'before:select-none',
);

function LineSpan({
    keyedLine,
    showLineNumbers,
}: {
    keyedLine: KeyedLine;
    showLineNumbers: boolean;
}) {
    return (
        <span className={showLineNumbers ? LINE_NUMBER_CLASSES : 'block'}>
            {keyedLine.tokens.length === 0
                ? '\n'
                : keyedLine.tokens.map(({ token, key }) => (
                      <TokenSpan key={key} token={token} />
                  ))}
        </span>
    );
}

const CodeBlockContext = createContext<{ code: string }>({ code: '' });

/**
 * One highlighter per language, which loads the themes as they are chosen.
 */
const highlighterCache = new Map<
    string,
    Promise<HighlighterGeneric<BundledLanguage, BundledTheme>>
>();

const tokensCache = new Map<string, TokenizedCode>();

const subscribers = new Map<string, Set<(result: TokenizedCode) => void>>();

const getTokensCacheKey = (
    code: string,
    language: BundledLanguage,
    theme: SyntaxThemeDefinition,
): string => {
    const start = code.slice(0, 100);
    const end = code.length > 100 ? code.slice(-100) : '';

    return `${theme.value}:${language}:${code.length}:${start}:${end}`;
};

const getHighlighter = async (
    language: BundledLanguage,
    theme: SyntaxThemeDefinition,
): Promise<HighlighterGeneric<BundledLanguage, BundledTheme>> => {
    let highlighter = highlighterCache.get(language);

    if (!highlighter) {
        highlighter = createHighlighter({
            langs: [language],
            themes: [theme.light, theme.dark],
        });
        highlighterCache.set(language, highlighter);
    }

    const loaded = await highlighter;
    const loadedThemes = loaded.getLoadedThemes();
    const missingThemes = [theme.light, theme.dark].filter(
        (name) => !loadedThemes.includes(name),
    );

    if (missingThemes.length > 0) {
        await loaded.loadTheme(...missingThemes);
    }

    return loaded;
};

const createRawTokens = (code: string): TokenizedCode => ({
    bg: 'transparent',
    fg: 'inherit',
    tokens: code
        .split('\n')
        .map((line) =>
            line === ''
                ? []
                : [{ color: 'inherit', content: line } as ThemedToken],
        ),
});

/**
 * The tokens of the code in the syntax theme when they are cached, null otherwise: the highlighting starts in the
 * background, and the callback receives its result.
 */
export const highlightCode = (
    code: string,
    language: BundledLanguage,
    theme: SyntaxThemeDefinition,
    callback?: (result: TokenizedCode) => void,
): TokenizedCode | null => {
    const tokensCacheKey = getTokensCacheKey(code, language, theme);
    const cached = tokensCache.get(tokensCacheKey);

    if (cached) {
        return cached;
    }

    if (callback) {
        if (!subscribers.has(tokensCacheKey)) {
            subscribers.set(tokensCacheKey, new Set());
        }

        subscribers.get(tokensCacheKey)?.add(callback);
    }

    getHighlighter(language, theme)
        .then((highlighter) => {
            const languageToUse = highlighter
                .getLoadedLanguages()
                .includes(language)
                ? language
                : 'text';

            const result = highlighter.codeToTokens(code, {
                lang: languageToUse,
                themes: { light: theme.light, dark: theme.dark },
            });

            const tokenized: TokenizedCode = {
                bg: result.bg ?? 'transparent',
                fg: result.fg ?? 'inherit',
                tokens: result.tokens,
            };

            tokensCache.set(tokensCacheKey, tokenized);
            subscribers
                .get(tokensCacheKey)
                ?.forEach((subscriber) => subscriber(tokenized));
            subscribers.delete(tokensCacheKey);
        })
        .catch((error) => {
            console.error('Failed to highlight code:', error);
            subscribers.delete(tokensCacheKey);
        });

    return null;
};

const CodeBlockBody = memo(
    ({
        tokenized,
        showLineNumbers,
        className,
    }: {
        tokenized: TokenizedCode;
        showLineNumbers: boolean;
        className?: string;
    }) => {
        const preStyle = useMemo(
            () => ({ backgroundColor: tokenized.bg, color: tokenized.fg }),
            [tokenized.bg, tokenized.fg],
        );

        const keyedLines = useMemo(
            () => addKeysToTokens(tokenized.tokens),
            [tokenized.tokens],
        );

        return (
            <pre
                className={cn(
                    'm-0 p-4 text-sm dark:bg-(--shiki-dark-bg)! dark:text-(--shiki-dark)!',
                    className,
                )}
                style={preStyle}
            >
                <code
                    className={cn(
                        'font-mono text-sm',
                        showLineNumbers &&
                            '[counter-increment:line_0] [counter-reset:line]',
                    )}
                >
                    {keyedLines.map((keyedLine) => (
                        <LineSpan
                            key={keyedLine.key}
                            keyedLine={keyedLine}
                            showLineNumbers={showLineNumbers}
                        />
                    ))}
                </code>
            </pre>
        );
    },
    (previousProps, nextProps) =>
        previousProps.tokenized === nextProps.tokenized &&
        previousProps.showLineNumbers === nextProps.showLineNumbers &&
        previousProps.className === nextProps.className,
);

CodeBlockBody.displayName = 'CodeBlockBody';

export function CodeBlockContainer({
    className,
    language,
    style,
    ...props
}: HTMLAttributes<HTMLDivElement> & { language: string }) {
    return (
        <div
            className={cn(
                'group relative w-full overflow-hidden rounded-md border bg-background text-foreground',
                className,
            )}
            data-language={language}
            style={{
                containIntrinsicSize: 'auto 200px',
                contentVisibility: 'auto',
                ...style,
            }}
            {...props}
        />
    );
}

export function CodeBlockHeader({
    children,
    className,
    ...props
}: HTMLAttributes<HTMLDivElement>) {
    return (
        <div
            className={cn(
                'flex items-center justify-between border-b bg-muted/80 px-3 py-2 text-xs text-muted-foreground',
                className,
            )}
            {...props}
        >
            {children}
        </div>
    );
}

export function CodeBlockTitle({
    children,
    className,
    ...props
}: HTMLAttributes<HTMLDivElement>) {
    return (
        <div className={cn('flex items-center gap-2', className)} {...props}>
            {children}
        </div>
    );
}

export function CodeBlockFilename({
    children,
    className,
    ...props
}: HTMLAttributes<HTMLSpanElement>) {
    return (
        <span className={cn('font-mono', className)} {...props}>
            {children}
        </span>
    );
}

export function CodeBlockActions({
    children,
    className,
    ...props
}: HTMLAttributes<HTMLDivElement>) {
    return (
        <div
            className={cn('-my-1 -mr-1 flex items-center gap-2', className)}
            {...props}
        >
            {children}
        </div>
    );
}

export function CodeBlockContent({
    code,
    language,
    showLineNumbers = false,
}: {
    code: string;
    language: BundledLanguage;
    showLineNumbers?: boolean;
}) {
    const { definition: theme } = useSyntaxTheme();
    const rawTokens = useMemo(() => createRawTokens(code), [code]);

    const syncTokens = useMemo(
        () => highlightCode(code, language, theme) ?? rawTokens,
        [code, language, theme, rawTokens],
    );

    // The tokens of another code, or of another theme, are ignored while the new ones are highlighted.
    const highlightKey = `${theme.value}:${language}:${code}`;
    const [asyncTokens, setAsyncTokens] = useState<{
        key: string;
        tokens: TokenizedCode;
    } | null>(null);

    useEffect(() => {
        let isCancelled = false;

        highlightCode(code, language, theme, (result) => {
            if (!isCancelled) {
                setAsyncTokens({ key: highlightKey, tokens: result });
            }
        });

        return () => {
            isCancelled = true;
        };
    }, [code, language, theme, highlightKey]);

    return (
        <div className="relative overflow-auto">
            <CodeBlockBody
                showLineNumbers={showLineNumbers}
                tokenized={
                    asyncTokens?.key === highlightKey
                        ? asyncTokens.tokens
                        : syncTokens
                }
            />
        </div>
    );
}

export function CodeBlock({
    code,
    language,
    showLineNumbers = false,
    className,
    children,
    ...props
}: CodeBlockProps) {
    const contextValue = useMemo(() => ({ code }), [code]);

    return (
        <CodeBlockContext.Provider value={contextValue}>
            <CodeBlockContainer
                className={className}
                language={language}
                {...props}
            >
                {children}
                <CodeBlockContent
                    code={code}
                    language={language}
                    showLineNumbers={showLineNumbers}
                />
            </CodeBlockContainer>
        </CodeBlockContext.Provider>
    );
}

export type CodeBlockCopyButtonProps = ComponentProps<typeof Button> & {
    onCopy?: () => void;
    onError?: (error: Error) => void;
    timeout?: number;
};

export function CodeBlockCopyButton({
    onCopy,
    onError,
    timeout = 2000,
    children,
    className,
    ...props
}: CodeBlockCopyButtonProps) {
    const [isCopied, setIsCopied] = useState(false);
    const timeoutRef = useRef<number>(0);
    const { code } = useContext(CodeBlockContext);

    const copyToClipboard = useCallback(async () => {
        if (typeof window === 'undefined' || !navigator?.clipboard?.writeText) {
            onError?.(new Error('Clipboard API not available'));

            return;
        }

        try {
            if (!isCopied) {
                await navigator.clipboard.writeText(code);
                setIsCopied(true);
                onCopy?.();
                timeoutRef.current = window.setTimeout(
                    () => setIsCopied(false),
                    timeout,
                );
            }
        } catch (error) {
            onError?.(error as Error);
        }
    }, [code, onCopy, onError, timeout, isCopied]);

    useEffect(() => () => window.clearTimeout(timeoutRef.current), []);

    const Icon = isCopied ? CheckIcon : CopyIcon;

    return (
        <Button
            className={cn('shrink-0', className)}
            onClick={copyToClipboard}
            size="icon"
            variant="ghost"
            {...props}
        >
            {children ?? <Icon size={14} />}
        </Button>
    );
}
