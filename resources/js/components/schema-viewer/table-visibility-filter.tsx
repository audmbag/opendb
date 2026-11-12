import { useLaravelReactI18n } from 'laravel-react-i18n';
import { EyeIcon, SearchIcon } from 'lucide-react';
import { useId, useMemo, useState } from 'react';
import Button from '@/components/form/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';

type Props = {
    tables: string[];
    hiddenTables: Set<string>;
    onChange: (hiddenTables: Set<string>) => void;
    disabled?: boolean;
};

/**
 * The tables drawn in the diagram, chosen one by one or all at once, with a search for a database with many tables.
 */
export default function TableVisibilityFilter({
    tables,
    hiddenTables,
    onChange,
    disabled,
}: Props) {
    const { t } = useLaravelReactI18n();
    const [search, setSearch] = useState('');
    const idPrefix = useId();
    const matchingTables = useMemo(() => {
        const query = search.trim().toLowerCase();

        return query
            ? tables.filter((table) => table.toLowerCase().includes(query))
            : tables;
    }, [search, tables]);
    const shownCount = tables.filter(
        (table) => !hiddenTables.has(table),
    ).length;

    const toggle = (table: string, isShown: boolean) => {
        const next = new Set(hiddenTables);

        if (isShown) {
            next.delete(table);
        } else {
            next.add(table);
        }

        onChange(next);
    };

    /**
     * Show or hide the tables matching the search, every table without one.
     */
    const setMatchingShown = (isShown: boolean) => {
        const next = new Set(hiddenTables);

        matchingTables.forEach((table) =>
            isShown ? next.delete(table) : next.add(table),
        );

        onChange(next);
    };

    return (
        <Popover onOpenChange={(open) => !open && setSearch('')}>
            <PopoverTrigger asChild>
                <Button variant="outline" size="sm" disabled={disabled}>
                    <EyeIcon />
                    {t('erd.visibility.count', {
                        shown: String(shownCount),
                        total: String(tables.length),
                    })}
                </Button>
            </PopoverTrigger>
            <PopoverContent align="start" className="w-72 gap-0 p-0">
                <div className="flex flex-col gap-2 border-b p-2">
                    <div className="relative">
                        <SearchIcon className="pointer-events-none absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder={t('erd.visibility.search')}
                            aria-label={t('erd.visibility.search')}
                            className="h-8 pl-8"
                        />
                    </div>
                    <div className="flex gap-1">
                        <Button
                            variant="ghost"
                            size="xs"
                            onClick={() => setMatchingShown(true)}
                        >
                            {t('erd.visibility.show_all')}
                        </Button>
                        <Button
                            variant="ghost"
                            size="xs"
                            onClick={() => setMatchingShown(false)}
                        >
                            {t('erd.visibility.hide_all')}
                        </Button>
                    </div>
                </div>
                <ul
                    aria-label={t('erd.visibility.label')}
                    className="max-h-72 overflow-y-auto p-1"
                >
                    {matchingTables.map((table) => {
                        const checkboxId = `${idPrefix}-${table}`;

                        return (
                            <li key={table}>
                                <label
                                    htmlFor={checkboxId}
                                    className="flex cursor-pointer items-center gap-2 rounded-sm px-2 py-1.5 text-sm hover:bg-muted"
                                >
                                    <Checkbox
                                        id={checkboxId}
                                        checked={!hiddenTables.has(table)}
                                        onCheckedChange={(checked) =>
                                            toggle(table, checked === true)
                                        }
                                    />
                                    <span
                                        className="min-w-0 truncate"
                                        title={table}
                                    >
                                        {table}
                                    </span>
                                </label>
                            </li>
                        );
                    })}
                    {matchingTables.length === 0 && (
                        <li className="px-2 py-4 text-center text-sm text-muted-foreground">
                            {t('erd.visibility.no_match')}
                        </li>
                    )}
                </ul>
            </PopoverContent>
        </Popover>
    );
}
