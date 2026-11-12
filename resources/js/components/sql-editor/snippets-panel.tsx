import { useLaravelReactI18n } from 'laravel-react-i18n';
import {
    CopyPlusIcon,
    EyeIcon,
    EyeOffIcon,
    PencilIcon,
    PlusIcon,
    SearchIcon,
    Trash2Icon,
    TriangleAlertIcon,
} from 'lucide-react';
import { useDeferredValue, useState } from 'react';
import AlertDialog from '@/components/alert-dialog';
import Button from '@/components/form/button';
import {
    builtinSnippets,
    snippetDetail,
} from '@/components/tables/console/sql-snippets';
import type {
    Snippet,
    SnippetConfig,
} from '@/components/tables/console/sql-snippets';
import { cn } from '@/lib/utils';
import SnippetDialog from './snippet-dialog';
import SqlSnippet from './sql-snippet';

type Props = {
    driver: string;
    config: SnippetConfig;
    /**
     * Save the whole configuration. Resolves with the message of the error, or null once saved.
     */
    onSave: (config: SnippetConfig) => Promise<string | null>;
    onInsert: (snippet: Snippet) => void;
};

type Editing = { title: string; initial: Snippet; index: number | null };

/**
 * The snippets of the editor: those the user wrote for the project, and those of opendb for its engine, which they
 * can hide or copy to change them. A click inserts a snippet at the cursor; typing its name in the editor does too.
 */
export default function SnippetsPanel({
    driver,
    config,
    onSave,
    onInsert,
}: Props) {
    const { t } = useLaravelReactI18n();
    const [search, setSearch] = useState('');
    const deferredSearch = useDeferredValue(search.trim().toLowerCase());
    const [editing, setEditing] = useState<Editing | null>(null);

    const matches = (snippet: Snippet) =>
        deferredSearch === '' ||
        `${snippet.label} ${snippetDetail(snippet.detail)} ${snippet.template}`
            .toLowerCase()
            .includes(deferredSearch);
    const own = config.snippets.filter(matches);
    const ownLabels = new Set(
        config.snippets.map((snippet) => snippet.label.toLowerCase()),
    );
    const builtin = builtinSnippets(driver).filter(matches);
    const editable = !config.invalid;

    const save = async (snippet: Snippet): Promise<string | null> => {
        if (!editing) {
            return null;
        }

        const snippets =
            editing.index === null
                ? [...config.snippets, snippet]
                : config.snippets.map((item, index) =>
                      index === editing.index ? snippet : item,
                  );
        const error = await onSave({ ...config, snippets });

        if (error === null) {
            setEditing(null);
        }

        return error;
    };

    const remove = async (index: number) => {
        const snippet = config.snippets[index];
        const confirmed = await AlertDialog.call({
            title: t('sql_editor.snippets.delete_title', {
                snippet: snippet.label,
            }),
            description: t('sql_editor.snippets.delete_description'),
            icon: Trash2Icon,
            variant: 'destructive',
            confirmText: t('common.delete'),
            cancelText: t('common.cancel'),
        });

        if (confirmed) {
            await onSave({
                ...config,
                snippets: config.snippets.filter((_, item) => item !== index),
            });
        }
    };

    const toggleHidden = (label: string) =>
        void onSave({
            ...config,
            hidden: config.hidden.includes(label)
                ? config.hidden.filter((item) => item !== label)
                : [...config.hidden, label],
        });

    return (
        <nav
            aria-label={t('sql_editor.snippets.title')}
            className="flex h-full min-h-0 min-w-0 flex-col overflow-hidden"
        >
            <div className="flex min-w-0 items-center gap-0.5 px-2 pt-2 pb-1.5">
                <h2 className="min-w-0 flex-1 truncate pl-1 text-sm font-medium">
                    {t('sql_editor.explorer.snippets')}
                </h2>
                <Button
                    variant="ghost"
                    size="icon-sm"
                    disabled={!editable}
                    onClick={() =>
                        setEditing({
                            title: t('sql_editor.snippets.new'),
                            initial: { label: '', detail: '', template: '' },
                            index: null,
                        })
                    }
                    aria-label={t('sql_editor.snippets.new')}
                    tooltip={t('sql_editor.snippets.new')}
                >
                    <PlusIcon />
                </Button>
            </div>

            <p className="mx-3 mb-2 text-xs text-muted-foreground">
                {t('sql_editor.snippets.help')}
            </p>

            {config.invalid && (
                <p className="mx-2 mb-2 flex gap-2 rounded-md border border-destructive/40 bg-destructive/5 p-2 text-xs text-destructive">
                    <TriangleAlertIcon className="size-3.5 shrink-0" />
                    {t('sql_editor.snippets.invalid')}
                </p>
            )}

            <label className="relative mx-2 mb-2 flex items-center">
                <span className="sr-only">
                    {t('sql_editor.snippets.search')}
                </span>
                <SearchIcon className="pointer-events-none absolute left-2.5 size-4 text-muted-foreground" />
                <input
                    type="search"
                    value={search}
                    onChange={(event) => setSearch(event.target.value)}
                    placeholder={t('common.search')}
                    className="h-8 w-full rounded-md border border-input bg-transparent pr-2 pl-8 text-sm outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 dark:bg-input/30"
                />
            </label>

            <div className="min-h-0 flex-1 overflow-y-auto px-2 pb-2">
                <h3 className="px-1 pt-1 pb-1.5 text-xs font-medium text-muted-foreground">
                    {t('sql_editor.snippets.mine')}
                </h3>
                {own.length === 0 ? (
                    <p className="px-1 pb-3 text-xs text-muted-foreground">
                        {config.snippets.length === 0
                            ? t('sql_editor.snippets.none')
                            : t('sql_editor.snippets.no_match')}
                    </p>
                ) : (
                    <ul className="mb-3 flex flex-col gap-1">
                        {own.map((snippet) => {
                            const index = config.snippets.indexOf(snippet);

                            return (
                                <SnippetItem
                                    key={snippet.label}
                                    snippet={snippet}
                                    onInsert={onInsert}
                                    actions={
                                        editable && (
                                            <>
                                                <Button
                                                    variant="ghost"
                                                    size="icon-sm"
                                                    className="size-6"
                                                    aria-label={t(
                                                        'sql_editor.snippets.edit_named',
                                                        {
                                                            snippet:
                                                                snippet.label,
                                                        },
                                                    )}
                                                    tooltip={t('common.edit')}
                                                    onClick={() =>
                                                        setEditing({
                                                            title: t(
                                                                'sql_editor.snippets.edit_named',
                                                                {
                                                                    snippet:
                                                                        snippet.label,
                                                                },
                                                            ),
                                                            initial: snippet,
                                                            index,
                                                        })
                                                    }
                                                >
                                                    <PencilIcon />
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="icon-sm"
                                                    className="size-6"
                                                    aria-label={t(
                                                        'sql_editor.snippets.delete_named',
                                                        {
                                                            snippet:
                                                                snippet.label,
                                                        },
                                                    )}
                                                    tooltip={t('common.delete')}
                                                    onClick={() =>
                                                        void remove(index)
                                                    }
                                                >
                                                    <Trash2Icon />
                                                </Button>
                                            </>
                                        )
                                    }
                                />
                            );
                        })}
                    </ul>
                )}

                <h3 className="px-1 pt-1 pb-1.5 text-xs font-medium text-muted-foreground">
                    {t('sql_editor.snippets.builtin')}
                </h3>
                <ul className="flex flex-col gap-1">
                    {builtin.map((snippet) => {
                        const hidden = config.hidden.includes(snippet.label);
                        const replaced = ownLabels.has(
                            snippet.label.toLowerCase(),
                        );

                        return (
                            <SnippetItem
                                key={snippet.label}
                                snippet={snippet}
                                muted={hidden || replaced}
                                badge={
                                    replaced
                                        ? t('sql_editor.snippets.replaced')
                                        : hidden
                                          ? t('sql_editor.snippets.hidden')
                                          : null
                                }
                                onInsert={onInsert}
                                actions={
                                    editable && (
                                        <>
                                            <Button
                                                variant="ghost"
                                                size="icon-sm"
                                                className="size-6"
                                                aria-label={t(
                                                    'sql_editor.snippets.copy_named',
                                                    { snippet: snippet.label },
                                                )}
                                                tooltip={t(
                                                    'sql_editor.snippets.copy',
                                                )}
                                                onClick={() =>
                                                    setEditing({
                                                        title: t(
                                                            'sql_editor.snippets.my_version',
                                                            {
                                                                snippet:
                                                                    snippet.label,
                                                            },
                                                        ),
                                                        initial: {
                                                            ...snippet,
                                                            detail: snippetDetail(
                                                                snippet.detail,
                                                            ),
                                                        },
                                                        index: null,
                                                    })
                                                }
                                            >
                                                <CopyPlusIcon />
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="icon-sm"
                                                className="size-6"
                                                aria-label={
                                                    hidden
                                                        ? t(
                                                              'sql_editor.snippets.show_named',
                                                              {
                                                                  snippet:
                                                                      snippet.label,
                                                              },
                                                          )
                                                        : t(
                                                              'sql_editor.snippets.hide_named',
                                                              {
                                                                  snippet:
                                                                      snippet.label,
                                                              },
                                                          )
                                                }
                                                tooltip={
                                                    hidden
                                                        ? t(
                                                              'sql_editor.snippets.show',
                                                          )
                                                        : t(
                                                              'sql_editor.snippets.hide',
                                                          )
                                                }
                                                onClick={() =>
                                                    toggleHidden(snippet.label)
                                                }
                                            >
                                                {hidden ? (
                                                    <EyeIcon />
                                                ) : (
                                                    <EyeOffIcon />
                                                )}
                                            </Button>
                                        </>
                                    )
                                }
                            />
                        );
                    })}
                </ul>
            </div>

            {editing && (
                <SnippetDialog
                    title={editing.title}
                    initial={editing.initial}
                    onSave={save}
                    onClose={() => setEditing(null)}
                />
            )}
        </nav>
    );
}

function SnippetItem({
    snippet,
    muted = false,
    badge = null,
    actions,
    onInsert,
}: {
    snippet: Snippet;
    muted?: boolean;
    badge?: string | null;
    actions: React.ReactNode;
    onInsert: (snippet: Snippet) => void;
}) {
    const { t } = useLaravelReactI18n();

    return (
        <li className="group/snippet relative">
            <button
                type="button"
                onClick={() => onInsert(snippet)}
                title={t('sql_editor.snippets.insert')}
                className={cn(
                    'flex w-full min-w-0 flex-col gap-1 rounded-md border border-transparent px-2 py-1.5 text-left transition-colors hover:border-border hover:bg-background focus-visible:border-ring focus-visible:outline-none',
                    muted && 'opacity-55',
                )}
            >
                <span className="flex min-w-0 items-center gap-1.5 pr-14 text-xs">
                    <span className="shrink-0 font-mono font-medium text-foreground">
                        {snippet.label}
                    </span>
                    <span className="min-w-0 truncate text-muted-foreground">
                        {snippetDetail(snippet.detail)}
                    </span>
                </span>
                {badge && (
                    <span className="text-[11px] text-muted-foreground italic">
                        {badge}
                    </span>
                )}
                <SqlSnippet sql={snippet.template} maxLines={3} raw />
            </button>
            <span className="absolute top-1 right-1 flex gap-0.5 opacity-0 group-hover/snippet:opacity-100 focus-within:opacity-100">
                {actions}
            </span>
        </li>
    );
}
