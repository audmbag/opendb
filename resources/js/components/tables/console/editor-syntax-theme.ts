import { HighlightStyle, syntaxHighlighting } from '@codemirror/language';
import type { TagStyle } from '@codemirror/language';
import type { Extension } from '@codemirror/state';
import { tags } from '@lezer/highlight';
import { EditorView } from '@uiw/react-codemirror';
import { useEffect, useState } from 'react';
import { bundledThemes, normalizeTheme } from 'shiki';
import type { BundledTheme, ThemeRegistrationResolved } from 'shiki';

type ScopeStyle = {
    color?: string;
    fontStyle?: string;
};

/**
 * The tags of the SQL language of CodeMirror, with the TextMate scopes Shiki gives to the same tokens of its SQL
 * grammar, the most specific first. As in Shiki, NULL is a keyword, and booleans and parameters keep the color of the
 * text.
 */
const TAG_SCOPES: { tag: TagStyle['tag']; scopes: string[] }[] = [
    {
        tag: [tags.keyword, tags.operatorKeyword, tags.null],
        scopes: ['keyword.other.DML.sql', 'keyword.other.sql', 'keyword'],
    },
    {
        tag: tags.typeName,
        scopes: ['storage.type.sql', 'storage.type', 'support.type'],
    },
    {
        tag: tags.standard(tags.name),
        scopes: [
            'support.function.aggregate.sql',
            'support.function',
            'entity.name.function',
        ],
    },
    {
        tag: [tags.string, tags.special(tags.string)],
        scopes: ['string.quoted.single.sql', 'string'],
    },
    {
        tag: tags.number,
        scopes: ['constant.numeric.sql', 'constant.numeric', 'constant'],
    },
    {
        tag: [tags.lineComment, tags.blockComment],
        scopes: ['comment.line.double-dash.sql', 'comment'],
    },
    {
        tag: tags.operator,
        scopes: ['keyword.operator.comparison.sql', 'keyword.operator'],
    },
    {
        tag: [tags.punctuation, tags.paren, tags.brace, tags.squareBracket],
        scopes: ['punctuation'],
    },
];

/**
 * The style of a scope in the theme, from its most specific rule as in TextMate: a selector matches the scope itself
 * or one of its parents (`keyword` matches `keyword.other.sql`), the longer one wins, the last one on a tie.
 */
export function resolveScopeStyle(
    theme: ThemeRegistrationResolved,
    scope: string,
): ScopeStyle | null {
    const matches = theme.settings
        .flatMap((rule, index) =>
            (Array.isArray(rule.scope) ? rule.scope : [rule.scope ?? ''])
                .flatMap((selector) => selector.split(','))
                .map((selector) => selector.trim())
                // Descendant and exclusion selectors depend on the surrounding tokens, unknown here.
                .filter(
                    (selector) =>
                        selector !== '' &&
                        !selector.startsWith('-') &&
                        !selector.includes(' ') &&
                        (scope === selector ||
                            scope.startsWith(`${selector}.`)),
                )
                .map((selector) => ({
                    specificity: selector.split('.').length,
                    index,
                    settings: rule.settings,
                })),
        )
        .filter(
            ({ settings }) =>
                settings.foreground !== undefined ||
                settings.fontStyle !== undefined,
        )
        .sort(
            (first, second) =>
                first.specificity - second.specificity ||
                first.index - second.index,
        );

    if (matches.length === 0) {
        return null;
    }

    // From the least to the most specific rule: each one overrides what it defines.
    return matches.reduce<ScopeStyle>(
        (style, { settings }) => ({
            color: settings.foreground ?? style.color,
            fontStyle: settings.fontStyle ?? style.fontStyle,
        }),
        {},
    );
}

const toTagStyle = (tag: TagStyle['tag'], style: ScopeStyle): TagStyle => ({
    tag,
    color: style.color,
    fontStyle: style.fontStyle?.includes('italic') ? 'italic' : undefined,
    fontWeight: style.fontStyle?.includes('bold') ? 'bold' : undefined,
});

/**
 * The editor in the colors of a Shiki theme: its text, its cursor and the highlighting of each token. The background
 * stays the one of the console.
 */
export function createEditorSyntaxTheme(
    theme: ThemeRegistrationResolved,
): Extension {
    const tagStyles = TAG_SCOPES.flatMap(({ tag, scopes }) => {
        const style = scopes
            .map((scope) => resolveScopeStyle(theme, scope))
            .find((resolved) => resolved?.color);

        return style ? [toTagStyle(tag, style)] : [];
    });

    return [
        EditorView.theme(
            {
                '&': { color: theme.fg },
                '.cm-content': { caretColor: theme.fg },
                '.cm-cursor, .cm-dropCursor': { borderLeftColor: theme.fg },
                '.cm-gutters': { color: 'var(--muted-foreground)' },
            },
            { dark: theme.type === 'dark' },
        ),
        syntaxHighlighting(HighlightStyle.define(tagStyles)),
    ];
}

const editorThemes = new Map<BundledTheme, Promise<Extension>>();

const loadEditorSyntaxTheme = (name: BundledTheme): Promise<Extension> => {
    let editorTheme = editorThemes.get(name);

    if (!editorTheme) {
        editorTheme = bundledThemes[name]().then((module) =>
            createEditorSyntaxTheme(normalizeTheme(module.default)),
        );
        editorThemes.set(name, editorTheme);
    }

    return editorTheme;
};

/**
 * The editor theme of a Shiki theme, or null while it loads.
 */
export function useEditorSyntaxTheme(name: BundledTheme): Extension | null {
    const [loaded, setLoaded] = useState<{
        name: BundledTheme;
        extension: Extension;
    } | null>(null);

    useEffect(() => {
        let isCancelled = false;

        loadEditorSyntaxTheme(name)
            .then((extension) => !isCancelled && setLoaded({ name, extension }))
            .catch((error) =>
                console.error('Failed to load the syntax theme:', error),
            );

        return () => {
            isCancelled = true;
        };
    }, [name]);

    return loaded?.name === name ? loaded.extension : null;
}
