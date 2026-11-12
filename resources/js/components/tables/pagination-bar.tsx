import { useLaravelReactI18n } from 'laravel-react-i18n';
import { ChevronLeftIcon, ChevronRightIcon } from 'lucide-react';
import { formatInteger } from '@/components/ai/artifacts/table-profile/format';
import Button from '@/components/form/button';
import {
    NativeSelect,
    NativeSelectOption,
} from '@/components/ui/native-select';
import type { RowCount } from './types';
import { PAGE_SIZES } from './view';

type Props = {
    page: number;
    perPage: number;
    rowsOnPage: number;
    hasMore: boolean;
    count: RowCount | null;
    onPageChange: (page: number) => void;
    onPerPageChange: (perPage: number) => void;
};

/**
 * Where the page is in the rows of the view. The total arrives after the rows, and is estimated on large tables without filter.
 */
export default function PaginationBar({
    page,
    perPage,
    rowsOnPage,
    hasMore,
    count,
    onPageChange,
    onPerPageChange,
}: Props) {
    const { t } = useLaravelReactI18n();
    const first = (page - 1) * perPage + 1;
    const last = (page - 1) * perPage + rowsOnPage;
    const lastPage =
        count && !count.estimated
            ? Math.max(1, Math.ceil(count.total / perPage))
            : null;

    return (
        <div className="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 border-t px-3 py-1.5 text-sm text-muted-foreground">
            <p className="tabular-nums" aria-live="polite">
                {rowsOnPage === 0
                    ? t('tables.pagination.no_rows')
                    : t('tables.pagination.rows', { first: formatInteger(first), last: formatInteger(last) })}
                {count ? (
                    <span>
                        {' '}
                        {count.estimated
                            ? t('tables.pagination.of_about', { total: formatInteger(count.total) })
                            : t('tables.pagination.of', { total: formatInteger(count.total) })}
                    </span>
                ) : (
                    <span className="text-muted-foreground/60"> {t('tables.pagination.of', { total: '…' })}</span>
                )}
            </p>

            <div className="flex items-center gap-2">
                <label className="flex items-center gap-2">
                    {t('tables.pagination.per_page')}
                    <NativeSelect
                        size="sm"
                        value={perPage}
                        onChange={(event) =>
                            onPerPageChange(Number(event.target.value))
                        }
                    >
                        {PAGE_SIZES.map((size) => (
                            <NativeSelectOption key={size} value={size}>
                                {size}
                            </NativeSelectOption>
                        ))}
                    </NativeSelect>
                </label>

                <span className="min-w-20 text-center tabular-nums">
                    Page {formatInteger(page)}
                    {lastPage !== null && ` / ${formatInteger(lastPage)}`}
                </span>

                <Button
                    variant="ghost"
                    size="icon-sm"
                    disabled={page <= 1}
                    onClick={() => onPageChange(page - 1)}
                    aria-label={t('common.previous_page')}
                >
                    <ChevronLeftIcon />
                </Button>
                <Button
                    variant="ghost"
                    size="icon-sm"
                    disabled={!hasMore}
                    onClick={() => onPageChange(page + 1)}
                    aria-label={t('common.next_page')}
                >
                    <ChevronRightIcon />
                </Button>
            </div>
        </div>
    );
}
