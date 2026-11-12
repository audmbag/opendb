import { useLaravelReactI18n } from 'laravel-react-i18n';
import {
    ArrowUpRightIcon,
    BanIcon,
    CopyIcon,
    CopyPlusIcon,
    EraserIcon,
    FileJsonIcon,
    FilterIcon,
    FilterXIcon,
    MessageSquareTextIcon,
    PanelRightOpenIcon,
    PencilIcon,
    Trash2Icon,
    Undo2Icon,
} from 'lucide-react';
import {
    ContextMenuContent,
    ContextMenuItem,
    ContextMenuSeparator,
    ContextMenuShortcut,
} from '@/components/ui/context-menu';
import type { CellValue } from '@/types/agent';
import type { TableColumn } from '../types';
import type { GridRow } from './data-grid';

export type GridActions = {
    copyValue: (row: GridRow, column: TableColumn) => void;
    copyRow: (row: GridRow) => void;
    setValue: (row: GridRow, column: TableColumn, value: CellValue) => void;
    /**
     * Filtering and duplicating belong to a table: a result of the SQL editor leaves them out.
     */
    filterByValue?: (
        column: TableColumn,
        value: CellValue,
        exclude: boolean,
    ) => void;
    openRow: (row: GridRow) => void;
    duplicateRow?: (row: GridRow) => void;
    toggleDelete: (row: GridRow) => void;
    revertRow: (row: GridRow) => void;
    askChat: (row: GridRow) => void;
    followReference: (row: GridRow, column: TableColumn) => void;
};

type Props = {
    row: GridRow;
    column: TableColumn;
    editable: boolean;
    canEditSavedRows: boolean;
    /**
     * Whether a saved row may be deleted, which needs DELETE rather than UPDATE: like changing it by default.
     */
    canDeleteSavedRows?: boolean;
    actions: GridActions;
    onEdit: () => void;
};

/**
 * What can be done with the selected cell and its row, opened by a right click.
 */
export default function GridContextMenu({
    row,
    column,
    editable,
    canEditSavedRows,
    canDeleteSavedRows = canEditSavedRows,
    actions,
    onEdit,
}: Props) {
    const { t } = useLaravelReactI18n();
    const value = row.values[column.name] ?? null;
    const canDeleteRow = row.state === 'inserted' || canDeleteSavedRows;
    const filterable = column.kind !== 'binary';

    return (
        <ContextMenuContent
            className="w-60"
            onCloseAutoFocus={(event) => event.preventDefault()}
        >
            <ContextMenuItem onSelect={() => actions.copyValue(row, column)}>
                <CopyIcon />
                {t('tables.grid.copy_value')}
                <ContextMenuShortcut>Ctrl+C</ContextMenuShortcut>
            </ContextMenuItem>
            <ContextMenuItem onSelect={() => actions.copyRow(row)}>
                <FileJsonIcon />
                {t('tables.grid.copy_row')}
            </ContextMenuItem>

            {editable && (
                <>
                    <ContextMenuSeparator />
                    <ContextMenuItem onSelect={onEdit}>
                        <PencilIcon />
                        {t('tables.grid.edit_value')}
                        <ContextMenuShortcut>{t('shortcuts.keys.enter')}</ContextMenuShortcut>
                    </ContextMenuItem>
                    {column.nullable && value !== null && (
                        <ContextMenuItem
                            onSelect={() => actions.setValue(row, column, null)}
                        >
                            <EraserIcon />
                            {t('tables.grid.set_null')}
                        </ContextMenuItem>
                    )}
                </>
            )}

            {filterable &&
                actions.filterByValue &&
                row.state !== 'inserted' && (
                    <>
                        <ContextMenuSeparator />
                        <ContextMenuItem
                            onSelect={() =>
                                actions.filterByValue?.(column, value, false)
                            }
                        >
                            <FilterIcon />
                            {value === null
                                ? t('tables.grid.filter_nulls')
                                : t('tables.grid.filter_value')}
                        </ContextMenuItem>
                        <ContextMenuItem
                            onSelect={() =>
                                actions.filterByValue?.(column, value, true)
                            }
                        >
                            <FilterXIcon />
                            {value === null
                                ? t('tables.grid.exclude_nulls')
                                : t('tables.grid.exclude_value')}
                        </ContextMenuItem>
                    </>
                )}

            <ContextMenuSeparator />
            {column.references && value !== null && row.state === 'saved' && (
                <ContextMenuItem
                    onSelect={() => actions.followReference(row, column)}
                >
                    <ArrowUpRightIcon />
                    {t('tables.grid.open_in', { table: column.references.table })}
                    <ContextMenuShortcut>Ctrl+{t('shortcuts.keys.click')}</ContextMenuShortcut>
                </ContextMenuItem>
            )}
            {row.state !== 'inserted' && row.rowKey && (
                <ContextMenuItem onSelect={() => actions.openRow(row)}>
                    <PanelRightOpenIcon />
                    {t('tables.grid.see_detail')}
                </ContextMenuItem>
            )}
            {actions.duplicateRow &&
                row.state !== 'deleted' && (
                    <ContextMenuItem
                        onSelect={() => actions.duplicateRow?.(row)}
                    >
                        <CopyPlusIcon />
                        {t('tables.grid.duplicate_row')}
                    </ContextMenuItem>
                )}
            {row.state === 'saved' && row.changed.length > 0 && (
                <ContextMenuItem onSelect={() => actions.revertRow(row)}>
                    <Undo2Icon />
                    {t('tables.grid.revert')}
                </ContextMenuItem>
            )}
            {canDeleteRow && (
                <ContextMenuItem
                    variant={
                        row.state === 'deleted' ? 'default' : 'destructive'
                    }
                    onSelect={() => actions.toggleDelete(row)}
                >
                    {row.state === 'deleted' ? <BanIcon /> : <Trash2Icon />}
                    {row.state === 'deleted'
                        ? t('tables.grid.keep_row')
                        : row.state === 'inserted'
                          ? t('tables.grid.remove_added_row')
                          : t('tables.grid.delete_row')}
                </ContextMenuItem>
            )}

            <ContextMenuSeparator />
            <ContextMenuItem onSelect={() => actions.askChat(row)}>
                <MessageSquareTextIcon />
                {t('tables.workspace.ask_chat')}
            </ContextMenuItem>
        </ContextMenuContent>
    );
}
