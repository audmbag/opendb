import { useLaravelReactI18n } from 'laravel-react-i18n';
import { CopyPlusIcon, TextCursorInputIcon } from 'lucide-react';
import { useState } from 'react';
import Button from '@/components/form/button';
import Input from '@/components/form/input';
import Modal from '@/components/modals/modal';
import ModalFooter from '@/components/modals/modal-footer';
import { Checkbox } from '@/components/ui/checkbox';
import { FieldError } from '@/components/ui/field';
import { Label } from '@/components/ui/label';
import TableDesignController from '@/wayfinder/actions/App/Http/Controllers/Tables/TableDesignController';
import type { TableRequestError } from '../api';
import { requestTableData } from '../api';

type Props = {
    projectId: string;
    table: string;
    mode: 'rename' | 'duplicate';
    onDone: (name: string) => void;
    onClose: () => void;
};

/**
 * The new name of a table, or of its copy, with the rows of the copy when asked for.
 */
export default function TableNameDialog({
    projectId,
    table,
    mode,
    onDone,
    onClose,
}: Props) {
    const { t } = useLaravelReactI18n();
    const [name, setName] = useState(
        mode === 'rename' ? table : `${table}_copie`,
    );
    const [withRows, setWithRows] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [processing, setProcessing] = useState(false);

    const submit = async (event: React.FormEvent) => {
        event.preventDefault();
        setProcessing(true);
        setError(null);

        const args = { project: projectId, table: encodeURIComponent(table) };

        try {
            await requestTableData(
                'post',
                mode === 'rename'
                    ? TableDesignController.rename.url(args)
                    : TableDesignController.duplicate.url(args),
                { data: { name, withRows } },
            );

            onDone(name);
        } catch (exception) {
            setError((exception as TableRequestError).first());
        } finally {
            setProcessing(false);
        }
    };

    return (
        <Modal
            open
            showHeader
            scrollable
            className="h-auto sm:max-w-md"
            onOpenChange={(open) => !open && !processing && onClose()}
            icon={mode === 'rename' ? TextCursorInputIcon : CopyPlusIcon}
            title={
                mode === 'rename'
                    ? t('tables.name_dialog.rename', { table })
                    : t('tables.name_dialog.duplicate', { table })
            }
            description={
                mode === 'rename'
                    ? t('tables.name_dialog.rename_description')
                    : t('tables.name_dialog.duplicate_description')
            }
        >
            <form onSubmit={submit} className="flex flex-col">
                <div className="flex flex-col gap-4 p-4">
                    <Input
                        id="table-name"
                        label={t('tables.designer.columns.name')}
                        value={name}
                        onChange={(event) => setName(event.target.value)}
                        error={error ?? undefined}
                        autoFocus
                        onFocus={(event) => event.target.select()}
                    />
                    {error && <FieldError>{error}</FieldError>}
                    {mode === 'duplicate' && (
                        <Label className="flex items-center gap-2 font-normal">
                            <Checkbox
                                checked={withRows}
                                onCheckedChange={(checked) =>
                                    setWithRows(checked === true)
                                }
                            />
                            {t('tables.name_dialog.with_rows')}
                        </Label>
                    )}
                </div>
                <ModalFooter>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={onClose}
                        disabled={processing}
                    >
                        {t('common.cancel')}
                    </Button>
                    <Button
                        type="submit"
                        disabled={
                            processing ||
                            name.trim() === '' ||
                            (mode === 'rename' && name === table)
                        }
                        processing={processing}
                    >
                        {mode === 'rename'
                            ? t('tables.workspace.rename')
                            : t('tables.workspace.duplicate')}
                    </Button>
                </ModalFooter>
            </form>
        </Modal>
    );
}
