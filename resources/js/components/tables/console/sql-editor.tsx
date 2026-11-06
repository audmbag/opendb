import { MariaSQL, MySQL, PostgreSQL, SQLite, sql } from '@codemirror/lang-sql';
import type { SQLDialect } from '@codemirror/lang-sql';
import CodeMirror, { EditorView, keymap, Prec } from '@uiw/react-codemirror';
import { useEffect, useMemo, useState } from 'react';
import type { RefObject } from 'react';
import { useEditorSyntaxTheme } from '@/components/tables/console/editor-syntax-theme';
import { useAppearance } from '@/hooks/use-apparence';
import { useSyntaxTheme } from '@/hooks/use-syntax-theme';
import { useValueAsRef } from '@/hooks/use-value-as-ref';
import { statementAt } from '@/lib/sql';
import { cn } from '@/lib/utils';

type Props = {
    value: string;
    onChange: (value: string) => void;
    /**
     * The driver of the project: mysql, mariadb, pgsql or sqlite.
     */
    driver: string;
    /**
     * The names of the tables, offered by the completion.
     */
    tables: string[];
    /**
     * The columns of each table, offered after `table.` once they are known. Without them only the tables are completed.
     */
    completion?: Record<string, string[]>;
    /**
     * The selected text, or null when nothing is selected: the console runs only it.
     */
    onSelectionChange: (selection: string | null) => void;
    /**
     * Ctrl+Enter.
     */
    onRun: () => void;
    /**
     * Shift+Alt+F, as in most code editors.
     */
    onFormat: () => void;
    /**
     * Ctrl+Shift+Enter: the statement under the cursor, selected so that the user sees what runs. Without it, the
     * shortcut does nothing.
     */
    onRunStatement?: (statement: string) => void;
    /**
     * A range to select once the value holds it, like a statement just written by the assistant: a new object selects
     * it again.
     */
    selection?: { from: number; to: number } | null;
    /**
     * Called once the editor exists, for the callers that insert text at the cursor.
     */
    onReady?: (view: EditorView) => void;
    className?: string;
};

const DIALECTS: Record<string, SQLDialect> = {
    mysql: MySQL,
    mariadb: MariaSQL,
    pgsql: PostgreSQL,
    sqlite: SQLite,
};

/**
 * Blends the editor in the console: the colors of the page, its monospace font, no frame.
 */
const consoleTheme = EditorView.theme({
    '&': { height: '100%', backgroundColor: 'transparent', fontSize: '14px' },
    '&.cm-focused': { outline: 'none' },
    '.cm-scroller': {
        fontFamily: 'var(--font-mono, ui-monospace, monospace)',
        lineHeight: '1.6',
    },
    '.cm-gutters': { backgroundColor: 'transparent', border: 'none' },
    // Translucent: the selection is drawn under the lines, an opaque active line would hide it.
    '.cm-activeLine, .cm-activeLineGutter': {
        backgroundColor: 'color-mix(in oklab, var(--muted) 45%, transparent)',
    },
    '.cm-selectionBackground, &.cm-focused > .cm-scroller > .cm-selectionLayer .cm-selectionBackground':
        {
            backgroundColor:
                'color-mix(in oklab, var(--primary) 22%, transparent)',
        },
    '.cm-content': { padding: '12px 0' },
});

/**
 * Ctrl+Enter, or Cmd+Enter, runs the script instead of inserting a line, Shift+Alt+F formats it. They call the
 * handlers of the moment.
 */
const shortcuts = (
    handlers: RefObject<{
        run: () => void;
        format: () => void;
        runStatement?: (statement: string) => void;
    }>,
) =>
    Prec.highest(
        keymap.of([
            {
                key: 'Mod-Shift-Enter',
                run: (view) => {
                    const runStatement = handlers.current.runStatement;
                    const bounds = runStatement
                        ? statementAt(
                              view.state.doc.toString(),
                              view.state.selection.main.head,
                          )
                        : null;

                    if (runStatement && bounds) {
                        view.dispatch({
                            selection: { anchor: bounds.from, head: bounds.to },
                            scrollIntoView: true,
                        });
                        runStatement(
                            view.state.sliceDoc(bounds.from, bounds.to),
                        );
                    }

                    return runStatement !== undefined;
                },
            },
            {
                key: 'Mod-Enter',
                run: () => {
                    handlers.current.run();

                    return true;
                },
            },
            {
                key: 'Shift-Alt-f',
                run: () => {
                    handlers.current.format();

                    return true;
                },
            },
        ]),
    );

/**
 * The SQL editor of the console: highlighting and completion of the keywords and tables in the dialect of the
 * database, Ctrl+Enter to run the script or its selected part, Shift+Alt+F to format it.
 */
export default function SqlEditor({
    value,
    onChange,
    driver,
    tables,
    onSelectionChange,
    onRun,
    onFormat,
    onRunStatement,
    selection = null,
    completion,
    onReady,
    className,
}: Props) {
    const { resolvedAppearance } = useAppearance();
    const { definition: syntaxTheme } = useSyntaxTheme();
    // The default light or dark theme of the editor while the syntax theme loads.
    const editorTheme = useEditorSyntaxTheme(
        resolvedAppearance === 'dark' ? syntaxTheme.dark : syntaxTheme.light,
    );
    // The shortcut is bound once: it calls the latest handler.
    const handlers = useValueAsRef({
        run: onRun,
        format: onFormat,
        runStatement: onRunStatement,
    });
    const [view, setView] = useState<EditorView | null>(null);

    // Runs after the editor took the new value: the range exists in its document.
    useEffect(() => {
        if (!view || !selection) {
            return;
        }

        const length = view.state.doc.length;

        view.dispatch({
            selection: {
                anchor: Math.min(selection.from, length),
                head: Math.min(selection.to, length),
            },
            scrollIntoView: true,
        });
        view.focus();
    }, [view, selection]);
    // A new list of the same tables does not configure the editor again.
    const tablesKey = tables.join('\n');

    const extensions = useMemo(
        () => [
            sql({
                dialect: DIALECTS[driver] ?? MySQL,
                schema: Object.fromEntries(
                    tablesKey
                        .split('\n')
                        .filter(Boolean)
                        .map((table) => [table, completion?.[table] ?? []]),
                ),
                upperCaseKeywords: true,
            }),
            shortcuts(handlers),
            // Above the light and dark themes of the editor, which paint their own background.
            Prec.highest(consoleTheme),
            EditorView.lineWrapping,
        ],
        [driver, tablesKey, completion, handlers],
    );

    return (
        <CodeMirror
            value={value}
            onChange={onChange}
            onCreateEditor={(created) => {
                setView(created);
                onReady?.(created);
            }}
            onUpdate={(update) => {
                if (update.selectionSet || update.docChanged) {
                    const { from, to } = update.state.selection.main;

                    onSelectionChange(
                        from === to ? null : update.state.sliceDoc(from, to),
                    );
                }
            }}
            extensions={extensions}
            theme={editorTheme ?? resolvedAppearance}
            placeholder="SELECT * FROM …"
            autoFocus
            aria-label="Requête SQL"
            basicSetup={{
                foldGutter: false,
                highlightActiveLineGutter: false,
            }}
            className={cn(
                'h-48 shrink-0 resize-y overflow-hidden border-b',
                className,
            )}
            height="100%"
        />
    );
}
