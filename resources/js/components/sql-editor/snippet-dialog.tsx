import { useLaravelReactI18n } from 'laravel-react-i18n';
import { CodeIcon } from 'lucide-react';
import { useState } from 'react';
import Button from '@/components/form/button';
import Input from '@/components/form/input';
import Textarea from '@/components/form/textarea';
import Modal from '@/components/modals/modal';
import ModalFooter from '@/components/modals/modal-footer';
import type { Snippet } from '@/components/tables/console/sql-snippets';

type Props = {
    /**
     * The snippet to change, or the one to start from, like a copy of a snippet of opendb.
     */
    initial: Snippet;
    title: string;
    /**
     * Resolves with the message of the error, or null once the snippet is saved.
     */
    onSave: (snippet: Snippet) => Promise<string | null>;
    onClose: () => void;
};

/**
 * The name typed in the editor to find a snippet, its description in the completion, and its SQL, whose `${name}`
 * marks the fields to fill in.
 */
export default function SnippetDialog({
    initial,
    title,
    onSave,
    onClose,
}: Props) {
    const { t } = useLaravelReactI18n();
    const [snippet, setSnippet] = useState(initial);
    const [error, setError] = useState<string | null>(null);
    const [saving, setSaving] = useState(false);

    const submit = async (event: React.FormEvent) => {
        event.preventDefault();
        setSaving(true);
        setError(await onSave(snippet));
        setSaving(false);
    };

    return (
        <Modal
            open
            showHeader
            scrollable
            className="h-auto sm:max-w-xl"
            onOpenChange={(open) => !open && onClose()}
            icon={CodeIcon}
            title={title}
            description={t('sql_editor.snippets.dialog_description', {
                field: '${field}',
                numbered: '${1:value}',
            })}
        >
            <form className="flex flex-col" onSubmit={submit}>
                <div className="flex flex-col gap-4 p-4">
                    <div className="grid gap-3 sm:grid-cols-[12rem_1fr]">
                        <Input
                            label={t('sql_editor.snippets.name')}
                            value={snippet.label}
                            placeholder={t(
                                'sql_editor.snippets.name_placeholder',
                            )}
                            autoFocus
                            autoComplete="off"
                            className="font-mono"
                            onChange={(event) =>
                                setSnippet({
                                    ...snippet,
                                    label: event.target.value,
                                })
                            }
                        />
                        <Input
                            label={t('sql_editor.snippets.description')}
                            value={snippet.detail}
                            placeholder={t(
                                'sql_editor.snippets.description_placeholder',
                            )}
                            autoComplete="off"
                            onChange={(event) =>
                                setSnippet({
                                    ...snippet,
                                    detail: event.target.value,
                                })
                            }
                        />
                    </div>
                    <Textarea
                        label="SQL"
                        value={snippet.template}
                        rows={8}
                        spellCheck={false}
                        description={t('sql_editor.snippets.sql_example', {
                            example:
                                'SELECT * FROM ${table} WHERE ${condition};',
                        })}
                        className="font-mono text-xs [&_textarea]:min-h-40"
                        onChange={(event) =>
                            setSnippet({
                                ...snippet,
                                template: event.target.value,
                            })
                        }
                    />

                    {error && (
                        <p className="text-sm text-destructive">{error}</p>
                    )}
                </div>

                <ModalFooter>
                    <Button type="button" variant="outline" onClick={onClose}>
                        {t('common.cancel')}
                    </Button>
                    <Button
                        type="submit"
                        processing={saving}
                        disabled={
                            snippet.label.trim() === '' ||
                            snippet.template.trim() === ''
                        }
                    >
                        {t('common.save')}
                    </Button>
                </ModalFooter>
            </form>
        </Modal>
    );
}
