import { useState } from 'react';
import Button from '@/components/form/button';
import Input from '@/components/form/input';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
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
        <Dialog open onOpenChange={(open) => !open && !processing && onClose()}>
            <DialogContent className="sm:max-w-md">
                <form onSubmit={submit} className="flex flex-col gap-4">
                    <DialogHeader>
                        <DialogTitle>
                            {mode === 'rename'
                                ? `Renommer ${table}`
                                : `Dupliquer ${table}`}
                        </DialogTitle>
                        <DialogDescription>
                            {mode === 'rename'
                                ? "Les clés étrangères des autres tables suivent le nouveau nom, mais pas les vues ni le code qui utilise l'ancien."
                                : 'La copie reprend les colonnes, la clé primaire, les index et les clés étrangères.'}
                        </DialogDescription>
                    </DialogHeader>
                    <Input
                        id="table-name"
                        label="Nom"
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
                            Copier aussi les lignes
                        </Label>
                    )}
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onClose}
                            disabled={processing}
                        >
                            Annuler
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
                            {mode === 'rename' ? 'Renommer' : 'Dupliquer'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
