import { useState } from 'react';
import { toast } from 'sonner';
import { trans } from '@/lib/i18n';
import TableDesignController from '@/wayfinder/actions/App/Http/Controllers/Tables/TableDesignController';
import type { TableRequestError } from './api';
import { requestTableData } from './api';
import type { TableStructure } from './types';

/**
 * A column moved after another one, or first when `after` is null, waiting for the user to read its statements.
 */
export type ColumnMove = {
    column: string;
    after: string | null;
    /**
     * The statements moving the column, null while they are read from the server.
     */
    statements: string[] | null;
    /**
     * The structure the move was asked from: the new order is shown on it until the structure is read again.
     */
    base: TableStructure;
    applying: boolean;
    applied: boolean;
};

/**
 * The names of the columns once `column` is moved after `after`, or first when `after` is null.
 */
export function reorderColumns(
    names: string[],
    column: string,
    after: string | null,
): string[] {
    const others = names.filter((name) => name !== column);
    const position = after === null ? 0 : others.indexOf(after) + 1;

    return [...others.slice(0, position), column, ...others.slice(position)];
}

/**
 * Moving a column of the table: it shows in its new place at once, its statements are read from the server and
 * shown, then run once confirmed. Cancelled, it goes back where it was.
 */
export function useColumnMove(
    projectId: string,
    structure: TableStructure,
    onMoved: () => void,
) {
    const [move, setMove] = useState<ColumnMove | null>(null);
    // The last column moved, lit up once in its new place.
    const [settled, setSettled] = useState<string | null>(null);
    const routeArgs = {
        project: projectId,
        table: encodeURIComponent(structure.name),
    };

    const fail = (exception: unknown) => {
        toast.error(trans('tables.structure.move_failed'), {
            description: (exception as TableRequestError).first(),
        });
    };

    const request = async (column: string, after: string | null) => {
        const names = structure.columns.map((item) => item.name);

        if (
            (move && move.base === structure && !move.applied) ||
            reorderColumns(names, column, after).join('\0') === names.join('\0')
        ) {
            return;
        }

        const requested: ColumnMove = {
            column,
            after,
            statements: null,
            base: structure,
            applying: false,
            applied: false,
        };
        setMove(requested);
        setSettled(null);

        try {
            const { statements } = await requestTableData<{
                statements: string[];
            }>('post', TableDesignController.previewColumnMove.url(routeArgs), {
                data: { column, after },
            });

            setMove(
                statements.length > 0 ? { ...requested, statements } : null,
            );
        } catch (exception) {
            setMove(null);
            fail(exception);
        }
    };

    const confirm = async () => {
        if (!move?.statements || move.applying) {
            return;
        }

        setMove({ ...move, applying: true });

        try {
            await requestTableData(
                'post',
                TableDesignController.moveColumn.url(routeArgs),
                {
                    data: { column: move.column, after: move.after },
                },
            );

            setMove({ ...move, applying: false, applied: true });
            setSettled(move.column);
            toast.success(
                trans('tables.structure.moved', { column: move.column }),
            );
            onMoved();
        } catch (exception) {
            setMove(null);
            fail(exception);
        }
    };

    const cancel = () => {
        if (!move?.applying) {
            setMove(null);
        }
    };

    // Once the structure is read again, it shows the column where it now is.
    const current = move && move.base === structure ? move : null;

    return { move: current, settled, request, confirm, cancel };
}
