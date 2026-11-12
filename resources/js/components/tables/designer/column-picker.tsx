import { useLaravelReactI18n } from 'laravel-react-i18n';
import { ChevronDownIcon } from 'lucide-react';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { cn } from '@/lib/utils';

type Props = {
    columns: string[];
    value: string[];
    onChange: (value: string[]) => void;
    disabled?: boolean;
    invalid?: boolean;
    placeholder?: string;
    'aria-label': string;
};

/**
 * Several columns chosen in the order they are ticked: the order of the columns of an index matters.
 */
export default function ColumnPicker({
    columns,
    value,
    onChange,
    disabled,
    invalid,
    placeholder,
    'aria-label': label,
}: Props) {
    const { t } = useLaravelReactI18n();
    const toggle = (column: string) =>
        onChange(
            value.includes(column)
                ? value.filter((item) => item !== column)
                : [...value, column],
        );

    return (
        <Popover>
            <PopoverTrigger asChild>
                <button
                    type="button"
                    disabled={disabled}
                    aria-label={label}
                    aria-invalid={invalid}
                    className={cn(
                        'flex h-8 w-full min-w-0 items-center gap-1 rounded-md border border-input bg-transparent px-2 text-left text-sm outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 disabled:opacity-50 aria-invalid:border-destructive dark:bg-input/30',
                    )}
                >
                    <span
                        className={cn(
                            'min-w-0 flex-1 truncate font-mono text-xs',
                            value.length === 0 &&
                                'font-sans text-muted-foreground',
                        )}
                    >
                        {value.length ? value.join(', ') : (placeholder ?? t('tables.designer.choose'))}
                    </span>
                    <ChevronDownIcon className="size-4 shrink-0 text-muted-foreground" />
                </button>
            </PopoverTrigger>
            <PopoverContent
                align="start"
                className="max-h-72 w-56 overflow-y-auto p-1"
            >
                {columns.length === 0 && (
                    <p className="px-2 py-1.5 text-sm text-muted-foreground">
                        {t('tables.designer.no_column')}
                    </p>
                )}
                {columns.map((column) => {
                    const position = value.indexOf(column);

                    return (
                        <label
                            key={column}
                            className="flex cursor-pointer items-center gap-2 rounded-sm px-2 py-1.5 text-sm hover:bg-muted"
                        >
                            <Checkbox
                                checked={position >= 0}
                                onCheckedChange={() => toggle(column)}
                            />
                            <span className="min-w-0 flex-1 truncate">
                                {column}
                            </span>
                            {position >= 0 && value.length > 1 && (
                                <span className="text-xs text-muted-foreground tabular-nums">
                                    {position + 1}
                                </span>
                            )}
                        </label>
                    );
                })}
            </PopoverContent>
        </Popover>
    );
}
