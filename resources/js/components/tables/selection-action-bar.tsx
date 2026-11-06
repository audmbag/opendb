import {
    BanIcon,
    ClipboardCopyIcon,
    CopyPlusIcon,
    MessageSquareTextIcon,
    PencilLineIcon,
    Trash2Icon,
    Undo2Icon,
    XIcon,
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import Button from '@/components/form/button';
import {
    ActionBar,
    ActionBarClose,
    ActionBarGroup,
    ActionBarItem,
    ActionBarSelection,
    ActionBarSeparator,
} from '@/components/ui/action-bar';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    NativeSelect,
    NativeSelectOption,
} from '@/components/ui/native-select';
import { isMysql, quoteIdentifier } from '@/lib/sql';
import { useTableGridStore } from '@/store/use-table-grid-store';
import type { CellValue } from '@/types/agent';
import { toCsv } from './csv';
import type { GridRow } from './data-grid/data-grid';
import { cellRawText } from './data-grid/format';
import type { TableColumn } from './types';

type Props = {
    table: string;
    /**
     * The driver of the project, to quote the names of the copied statements.
     */
    driver: string;
    rows: GridRow[];
    columns: TableColumn[];
    canEditSavedRows: boolean;
    onDelete: (rows: GridRow[]) => void;
    onDuplicate: (rows: GridRow[]) => void;
    onRevert: (rows: GridRow[]) => void;
    onSetValue: (
        rows: GridRow[],
        column: TableColumn,
        value: CellValue,
    ) => void;
    onAskChat: (rows: GridRow[]) => void;
};

/**
 * A value written in a statement: TRUE and FALSE are understood by the four databases, and MySQL reads a backslash
 * in a string as an escape.
 */
const sqlLiteral = (value: CellValue, driver: string): string => {
    if (value === null) {
        return 'NULL';
    }

    if (typeof value === 'boolean') {
        return value ? 'TRUE' : 'FALSE';
    }

    if (typeof value === 'number') {
        return String(value);
    }

    const text = cellRawText(value).replace(/'/g, "''");

    return `'${isMysql(driver) ? text.replace(/\\/g, '\\\\') : text}'`;
};

/**
 * The rows ticked in the grid and what can be done to all of them at once, in a bar at the bottom of the screen.
 * Deleting, duplicating or setting a value only adds pending changes: they are reviewed before being applied.
 */
export default function SelectionActionBar({
    table,
    driver,
    rows,
    columns,
    canEditSavedRows,
    onDelete,
    onDuplicate,
    onRevert,
    onSetValue,
    onAskChat,
}: Props) {
    const selected = useTableGridStore((state) => state.selected);
    const clearSelection = useTableGridStore((state) => state.clearSelection);
    const [editing, setEditing] = useState(false);

    const selection = rows.filter((row) => row.id in selected);
    const count = selection.length;
    const changeable = selection.filter(
        (row) => row.state === 'inserted' || canEditSavedRows,
    );
    const allDeleted =
        changeable.length > 0 &&
        changeable.every((row) => row.state === 'deleted');
    const changed = selection.some(
        (row) =>
            row.state === 'deleted' ||
            (row.state === 'saved' && row.changed.length > 0),
    );

    const copy = (format: 'json' | 'csv' | 'sql') => {
        const names = columns.map((column) => column.name);
        const text =
            format === 'json'
                ? JSON.stringify(
                      selection.map((row) => row.values),
                      null,
                      2,
                  )
                : format === 'csv'
                  ? toCsv(
                        names,
                        selection.map((row) =>
                            names.map((name) =>
                                cellRawText(row.values[name] ?? null),
                            ),
                        ),
                    )
                  : selection
                        .map(
                            (row) =>
                                `INSERT INTO ${quoteIdentifier(table, driver)} (${names
                                    .map((name) =>
                                        quoteIdentifier(name, driver),
                                    )
                                    .join(', ')}) VALUES (${names
                                    .map((name) =>
                                        sqlLiteral(
                                            row.values[name] ?? null,
                                            driver,
                                        ),
                                    )
                                    .join(', ')});`,
                        )
                        .join('\n');

        navigator.clipboard
            .writeText(text)
            .then(() =>
                toast.success(
                    `${count} ligne${count > 1 ? 's' : ''} copiée${count > 1 ? 's' : ''}`,
                    { duration: 1500 },
                ),
            )
            .catch(() => toast.error('Le presse-papiers est inaccessible.'));
    };

    return (
        <>
            <ActionBar
                open={count > 0 && !editing}
                onOpenChange={(open) => !open && clearSelection()}
            >
                <ActionBarSelection>
                    <span>
                        {count} ligne{count > 1 ? 's' : ''} sélectionnée
                        {count > 1 ? 's' : ''}
                    </span>
                    <ActionBarSeparator />
                    <ActionBarClose aria-label="Désélectionner">
                        <XIcon />
                    </ActionBarClose>
                </ActionBarSelection>
                <ActionBarSeparator />
                <ActionBarGroup>
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <ActionBarItem
                                onSelect={(event) => event.preventDefault()}
                            >
                                <ClipboardCopyIcon />
                                Copier
                            </ActionBarItem>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent side="top" align="start">
                            <DropdownMenuItem onSelect={() => copy('json')}>
                                En JSON
                            </DropdownMenuItem>
                            <DropdownMenuItem onSelect={() => copy('csv')}>
                                En CSV
                            </DropdownMenuItem>
                            <DropdownMenuItem onSelect={() => copy('sql')}>
                                En instructions INSERT
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                    <ActionBarItem
                        onSelect={(event) => {
                            event.preventDefault();
                            setEditing(true);
                        }}
                        disabled={changeable.length === 0}
                    >
                        <PencilLineIcon />
                        Modifier une colonne
                    </ActionBarItem>
                    <ActionBarItem onSelect={() => onDuplicate(selection)}>
                        <CopyPlusIcon />
                        Dupliquer
                    </ActionBarItem>
                    {changed && (
                        <ActionBarItem
                            onSelect={(event) => {
                                event.preventDefault();
                                onRevert(selection);
                            }}
                        >
                            <Undo2Icon />
                            Annuler les modifications
                        </ActionBarItem>
                    )}
                    <ActionBarItem onSelect={() => onAskChat(selection)}>
                        <MessageSquareTextIcon />
                        Demander au chat
                    </ActionBarItem>
                    <ActionBarItem
                        variant={allDeleted ? 'secondary' : 'destructive'}
                        onSelect={() => onDelete(changeable)}
                        disabled={changeable.length === 0}
                    >
                        {allDeleted ? <BanIcon /> : <Trash2Icon />}
                        {allDeleted ? 'Garder' : 'Supprimer'}
                    </ActionBarItem>
                </ActionBarGroup>
            </ActionBar>

            {editing && (
                <BulkValueDialog
                    columns={columns.filter(
                        (column) =>
                            column.editable &&
                            !(column.primary && canEditSavedRows),
                    )}
                    count={changeable.length}
                    onCancel={() => setEditing(false)}
                    onConfirm={(column, value) => {
                        onSetValue(changeable, column, value);
                        setEditing(false);
                    }}
                />
            )}
        </>
    );
}

/**
 * One value given to a column of every selected row, kept as a pending change of each of them.
 */
function BulkValueDialog({
    columns,
    count,
    onCancel,
    onConfirm,
}: {
    columns: TableColumn[];
    count: number;
    onCancel: () => void;
    onConfirm: (column: TableColumn, value: CellValue) => void;
}) {
    const [columnName, setColumnName] = useState(columns[0]?.name ?? '');
    const [value, setValue] = useState('');
    const [isNull, setIsNull] = useState(false);
    const column = columns.find((item) => item.name === columnName);

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        if (!column) {
            return;
        }

        onConfirm(
            column,
            isNull
                ? null
                : column.kind === 'boolean'
                  ? value === 'true'
                  : value,
        );
    };

    return (
        <Dialog open onOpenChange={(open) => !open && onCancel()}>
            <DialogContent className="sm:max-w-md">
                <form onSubmit={submit} className="flex flex-col gap-4">
                    <DialogHeader>
                        <DialogTitle>Modifier une colonne</DialogTitle>
                        <DialogDescription>
                            La valeur est donnée à {count} ligne
                            {count > 1 ? 's' : ''}, en modification en attente :
                            vous la vérifierez avant de l'appliquer.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="flex flex-col gap-2">
                        <Label htmlFor="bulk-column">Colonne</Label>
                        <NativeSelect
                            id="bulk-column"
                            className="w-full"
                            value={columnName}
                            onChange={(event) =>
                                setColumnName(event.target.value)
                            }
                        >
                            {columns.map((item) => (
                                <NativeSelectOption
                                    key={item.name}
                                    value={item.name}
                                >
                                    {item.name} ({item.db_type})
                                </NativeSelectOption>
                            ))}
                        </NativeSelect>
                    </div>
                    <div className="flex flex-col gap-2">
                        <Label htmlFor="bulk-value">Valeur</Label>
                        {column?.kind === 'boolean' ||
                        column?.kind === 'enum' ? (
                            <NativeSelect
                                id="bulk-value"
                                className="w-full"
                                value={value}
                                disabled={isNull}
                                onChange={(event) =>
                                    setValue(event.target.value)
                                }
                            >
                                <NativeSelectOption value="" disabled>
                                    Choisir
                                </NativeSelectOption>
                                {(column.kind === 'boolean'
                                    ? ['true', 'false']
                                    : (column.options ?? [])
                                ).map((option) => (
                                    <NativeSelectOption
                                        key={option}
                                        value={option}
                                    >
                                        {option}
                                    </NativeSelectOption>
                                ))}
                            </NativeSelect>
                        ) : (
                            <Input
                                id="bulk-value"
                                value={value}
                                disabled={isNull}
                                onChange={(event) =>
                                    setValue(event.target.value)
                                }
                                autoFocus
                            />
                        )}
                        {column?.nullable && (
                            <Label className="flex items-center gap-2 text-sm font-normal">
                                <input
                                    type="checkbox"
                                    checked={isNull}
                                    onChange={(event) =>
                                        setIsNull(event.target.checked)
                                    }
                                    className="size-4 accent-primary"
                                />
                                Mettre à NULL
                            </Label>
                        )}
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onCancel}
                        >
                            Annuler
                        </Button>
                        <Button type="submit" disabled={!column}>
                            Appliquer à {count} ligne{count > 1 ? 's' : ''}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
