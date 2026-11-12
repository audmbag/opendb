import { useLaravelReactI18n } from 'laravel-react-i18n';
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
import Modal from '@/components/modals/modal';
import ModalFooter from '@/components/modals/modal-footer';
import {
    ActionBar,
    ActionBarClose,
    ActionBarGroup,
    ActionBarItem,
    ActionBarSelection,
    ActionBarSeparator,
} from '@/components/ui/action-bar';
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
    /**
     * Whether a saved row may be deleted, which needs DELETE on the table rather than UPDATE: like changing them by default.
     */
    canDeleteSavedRows?: boolean;
    /**
     * Whether rows may be added, which duplicating does.
     */
    canInsert?: boolean;
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
    canDeleteSavedRows = canEditSavedRows,
    canInsert = true,
    onDelete,
    onDuplicate,
    onRevert,
    onSetValue,
    onAskChat,
}: Props) {
    const { t, tChoice } = useLaravelReactI18n();
    const selected = useTableGridStore((state) => state.selected);
    const clearSelection = useTableGridStore((state) => state.clearSelection);
    const [editing, setEditing] = useState(false);

    const selection = rows.filter((row) => row.id in selected);
    const count = selection.length;
    const changeable = selection.filter(
        (row) => row.state === 'inserted' || canEditSavedRows,
    );
    const deletable = selection.filter(
        (row) => row.state === 'inserted' || canDeleteSavedRows,
    );
    const allDeleted =
        deletable.length > 0 &&
        deletable.every((row) => row.state === 'deleted');
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
                toast.success(tChoice('tables.selection.copied', count), {
                    duration: 1500,
                }),
            )
            .catch(() => toast.error(t('tables.selection.clipboard_failed')));
    };

    return (
        <>
            <ActionBar
                open={count > 0 && !editing}
                onOpenChange={(open) => !open && clearSelection()}
            >
                <ActionBarSelection>
                    <span>{tChoice('tables.selection.selected', count)}</span>
                    <ActionBarSeparator />
                    <ActionBarClose aria-label={t('tables.selection.clear')}>
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
                                {t('common.copy')}
                            </ActionBarItem>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent side="top" align="start">
                            <DropdownMenuItem onSelect={() => copy('json')}>
                                {t('tables.console.as_format', {
                                    format: 'JSON',
                                })}
                            </DropdownMenuItem>
                            <DropdownMenuItem onSelect={() => copy('csv')}>
                                {t('tables.console.as_format', {
                                    format: 'CSV',
                                })}
                            </DropdownMenuItem>
                            <DropdownMenuItem onSelect={() => copy('sql')}>
                                {t('tables.selection.as_insert')}
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
                        {t('tables.selection.edit_column')}
                    </ActionBarItem>
                    {canInsert && (
                        <ActionBarItem onSelect={() => onDuplicate(selection)}>
                            <CopyPlusIcon />
                            {t('tables.workspace.duplicate')}
                        </ActionBarItem>
                    )}
                    {changed && (
                        <ActionBarItem
                            onSelect={(event) => {
                                event.preventDefault();
                                onRevert(selection);
                            }}
                        >
                            <Undo2Icon />
                            {t('tables.grid.revert')}
                        </ActionBarItem>
                    )}
                    <ActionBarItem onSelect={() => onAskChat(selection)}>
                        <MessageSquareTextIcon />
                        {t('tables.workspace.ask_chat')}
                    </ActionBarItem>
                    <ActionBarItem
                        variant={allDeleted ? 'secondary' : 'destructive'}
                        onSelect={() => onDelete(deletable)}
                        disabled={deletable.length === 0}
                    >
                        {allDeleted ? <BanIcon /> : <Trash2Icon />}
                        {allDeleted
                            ? t('tables.designer.keep')
                            : t('common.delete')}
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
    const { t, tChoice } = useLaravelReactI18n();
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
        <Modal
            open
            showHeader
            scrollable
            className="h-auto sm:max-w-md"
            onOpenChange={(open) => !open && onCancel()}
            icon={PencilLineIcon}
            title={t('tables.selection.edit_column')}
            description={tChoice('tables.selection.bulk_description', count)}
        >
            <form onSubmit={submit} className="flex flex-col">
                <div className="flex flex-col gap-4 p-4">
                    <div className="flex flex-col gap-2">
                        <Label htmlFor="bulk-column">
                            {t('ai.profile.headers.column')}
                        </Label>
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
                        <Label htmlFor="bulk-value">{t('common.value')}</Label>
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
                                    {t('tables.designer.choose')}
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
                                {t('tables.grid.set_null')}
                            </Label>
                        )}
                    </div>
                </div>
                <ModalFooter>
                    <Button type="button" variant="outline" onClick={onCancel}>
                        {t('common.cancel')}
                    </Button>
                    <Button type="submit" disabled={!column}>
                        {tChoice('tables.selection.apply_to', count)}
                    </Button>
                </ModalFooter>
            </form>
        </Modal>
    );
}
