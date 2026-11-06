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
    filterByValue: (
        column: TableColumn,
        value: CellValue,
        exclude: boolean,
    ) => void;
    openRow: (row: GridRow) => void;
    duplicateRow: (row: GridRow) => void;
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
    actions,
    onEdit,
}: Props) {
    const value = row.values[column.name] ?? null;
    const canChangeRow = row.state === 'inserted' || canEditSavedRows;
    const filterable = column.kind !== 'binary';

    return (
        <ContextMenuContent
            className="w-60"
            onCloseAutoFocus={(event) => event.preventDefault()}
        >
            <ContextMenuItem onSelect={() => actions.copyValue(row, column)}>
                <CopyIcon />
                Copier la valeur
                <ContextMenuShortcut>Ctrl+C</ContextMenuShortcut>
            </ContextMenuItem>
            <ContextMenuItem onSelect={() => actions.copyRow(row)}>
                <FileJsonIcon />
                Copier la ligne en JSON
            </ContextMenuItem>

            {editable && (
                <>
                    <ContextMenuSeparator />
                    <ContextMenuItem onSelect={onEdit}>
                        <PencilIcon />
                        Modifier la valeur
                        <ContextMenuShortcut>Entrée</ContextMenuShortcut>
                    </ContextMenuItem>
                    {column.nullable && value !== null && (
                        <ContextMenuItem
                            onSelect={() => actions.setValue(row, column, null)}
                        >
                            <EraserIcon />
                            Mettre à NULL
                        </ContextMenuItem>
                    )}
                </>
            )}

            {filterable && row.state !== 'inserted' && (
                <>
                    <ContextMenuSeparator />
                    <ContextMenuItem
                        onSelect={() =>
                            actions.filterByValue(column, value, false)
                        }
                    >
                        <FilterIcon />
                        {value === null
                            ? 'Filtrer les NULL'
                            : 'Filtrer par cette valeur'}
                    </ContextMenuItem>
                    <ContextMenuItem
                        onSelect={() =>
                            actions.filterByValue(column, value, true)
                        }
                    >
                        <FilterXIcon />
                        {value === null
                            ? 'Exclure les NULL'
                            : 'Exclure cette valeur'}
                    </ContextMenuItem>
                </>
            )}

            <ContextMenuSeparator />
            {column.references && value !== null && row.state === 'saved' && (
                <ContextMenuItem
                    onSelect={() => actions.followReference(row, column)}
                >
                    <ArrowUpRightIcon />
                    Ouvrir dans {column.references.table}
                    <ContextMenuShortcut>Ctrl+clic</ContextMenuShortcut>
                </ContextMenuItem>
            )}
            {row.state !== 'inserted' && row.rowKey && (
                <ContextMenuItem onSelect={() => actions.openRow(row)}>
                    <PanelRightOpenIcon />
                    Voir le détail
                </ContextMenuItem>
            )}
            {canChangeRow && row.state !== 'deleted' && (
                <ContextMenuItem onSelect={() => actions.duplicateRow(row)}>
                    <CopyPlusIcon />
                    Dupliquer la ligne
                </ContextMenuItem>
            )}
            {row.state === 'saved' && row.changed.length > 0 && (
                <ContextMenuItem onSelect={() => actions.revertRow(row)}>
                    <Undo2Icon />
                    Annuler les modifications
                </ContextMenuItem>
            )}
            {canChangeRow && (
                <ContextMenuItem
                    variant={
                        row.state === 'deleted' ? 'default' : 'destructive'
                    }
                    onSelect={() => actions.toggleDelete(row)}
                >
                    {row.state === 'deleted' ? <BanIcon /> : <Trash2Icon />}
                    {row.state === 'deleted'
                        ? 'Garder la ligne'
                        : row.state === 'inserted'
                          ? 'Retirer la ligne ajoutée'
                          : 'Supprimer la ligne'}
                </ContextMenuItem>
            )}

            <ContextMenuSeparator />
            <ContextMenuItem onSelect={() => actions.askChat(row)}>
                <MessageSquareTextIcon />
                Demander au chat
            </ContextMenuItem>
        </ContextMenuContent>
    );
}
