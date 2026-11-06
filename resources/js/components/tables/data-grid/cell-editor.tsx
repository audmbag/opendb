import { useEffect, useRef, useState } from 'react';
import { cn } from '@/lib/utils';
import type { CellValue } from '@/types/agent';
import type { TableColumn } from '../types';
import { booleanOf, cellRawText } from './format';

export type EditorMove = 'down' | 'up' | 'right' | 'left' | null;

type Props = {
    column: TableColumn;
    value: CellValue;
    initialInput: string | null;
    width: number;
    onCommit: (value: CellValue, move: EditorMove) => void;
    onCancel: () => void;
};

const MULTILINE_KINDS = ['text', 'json'];

/**
 * The value typed in the editor, as it is sent to the server: text stays text, the server converts it for the column.
 */
const parseInput = (column: TableColumn, input: string): CellValue => {
    if (column.kind === 'boolean') {
        return input === '' ? null : input === 'true';
    }

    if (column.kind === 'enum' && input === '') {
        return null;
    }

    return input;
};

/**
 * The editor of the selected cell, over the cell itself. Enter or Tab keep the value and move on, Escape leaves it unchanged.
 * Long text and JSON open a larger box, where Enter adds a line and Ctrl+Enter keeps the value.
 */
export default function CellEditor({
    column,
    value,
    initialInput,
    width,
    onCommit,
    onCancel,
}: Props) {
    const multiline = MULTILINE_KINDS.includes(column.kind);
    const [input, setInput] = useState(
        () =>
            initialInput ??
            (column.kind === 'boolean'
                ? String(booleanOf(value) ?? '')
                : cellRawText(value)),
    );
    const fieldRef = useRef<
        HTMLInputElement & HTMLTextAreaElement & HTMLSelectElement
    >(null);
    const settled = useRef(false);

    useEffect(() => {
        const field: HTMLElement | null = fieldRef.current;

        field?.focus();

        // The value is selected so that typing replaces it, except for a date, whose picker has no selection.
        if (
            initialInput === null &&
            (field instanceof HTMLTextAreaElement ||
                (field instanceof HTMLInputElement && field.type === 'text'))
        ) {
            field.select();
        }
    }, [initialInput]);

    const commit = (next: CellValue, move: EditorMove) => {
        if (!settled.current) {
            settled.current = true;
            onCommit(next, move);
        }
    };

    const cancel = () => {
        if (!settled.current) {
            settled.current = true;
            onCancel();
        }
    };

    const handleKeyDown = (event: React.KeyboardEvent) => {
        event.stopPropagation();

        if (event.key === 'Escape') {
            event.preventDefault();
            cancel();
        } else if (event.key === 'Tab') {
            event.preventDefault();
            commit(
                parseInput(column, input),
                event.shiftKey ? 'left' : 'right',
            );
        } else if (
            event.key === 'Enter' &&
            (!multiline || event.ctrlKey || event.metaKey)
        ) {
            event.preventDefault();
            commit(parseInput(column, input), event.shiftKey ? 'up' : 'down');
        }
    };

    const fieldProps = {
        ref: fieldRef,
        value: input,
        onKeyDown: handleKeyDown,
        onBlur: () => commit(parseInput(column, input), null),
        'aria-label': `Valeur de ${column.name}`,
    };

    const setNull = (event: React.MouseEvent) => {
        // Kept on mouse down, before the field loses the focus and keeps its text.
        event.preventDefault();
        commit(null, null);
    };

    const nullButton = column.nullable && (
        <button
            type="button"
            onMouseDown={setNull}
            className="shrink-0 rounded-sm px-1.5 text-xs text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
        >
            NULL
        </button>
    );

    if (multiline) {
        return (
            <div
                className="absolute top-0 left-0 z-30 flex flex-col overflow-hidden rounded-md border bg-popover shadow-lg ring-2 ring-sidebar-primary"
                style={{ width: Math.max(width, 360) }}
            >
                <textarea
                    {...fieldProps}
                    onChange={(event) => setInput(event.target.value)}
                    spellCheck={column.kind === 'text'}
                    className={cn(
                        'h-40 resize-none bg-transparent p-2 text-sm outline-none',
                        column.kind === 'json' && 'font-mono text-xs',
                    )}
                />
                <div className="flex items-center justify-between gap-2 border-t px-2 py-1 text-xs text-muted-foreground">
                    <span>Ctrl+Entrée valide, Échap annule</span>
                    {nullButton}
                </div>
            </div>
        );
    }

    const fieldClass =
        'h-full min-w-0 flex-1 bg-transparent px-2 text-sm outline-none';

    return (
        <div className="absolute inset-0 z-30 flex items-center bg-background ring-2 ring-sidebar-primary ring-inset">
            {column.kind === 'boolean' || column.kind === 'enum' ? (
                <select
                    {...fieldProps}
                    onChange={(event) =>
                        commit(parseInput(column, event.target.value), null)
                    }
                    className={fieldClass}
                >
                    {column.nullable && <option value="">NULL</option>}
                    {column.kind === 'boolean' ? (
                        <>
                            <option value="true">true</option>
                            <option value="false">false</option>
                        </>
                    ) : (
                        column.options?.map((option) => (
                            <option key={option} value={option}>
                                {option}
                            </option>
                        ))
                    )}
                </select>
            ) : (
                <input
                    {...fieldProps}
                    type={column.kind === 'date' ? 'date' : 'text'}
                    inputMode={
                        column.kind === 'integer'
                            ? 'numeric'
                            : column.kind === 'decimal'
                              ? 'decimal'
                              : undefined
                    }
                    maxLength={column.max_length ?? undefined}
                    onChange={(event) => setInput(event.target.value)}
                    className={cn(
                        fieldClass,
                        (column.kind === 'integer' ||
                            column.kind === 'decimal') &&
                            'text-right tabular-nums',
                    )}
                />
            )}
            {nullButton}
        </div>
    );
}
