import { useLaravelReactI18n } from 'laravel-react-i18n';
import { ChevronDownIcon } from 'lucide-react';
import { useState } from 'react';
import ChartView from '@/components/ai/artifacts/chart-view';
import Button from '@/components/form/button';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    NativeSelect,
    NativeSelectOption,
} from '@/components/ui/native-select';
import { Switch } from '@/components/ui/switch';
import type { CellValue, ChartType } from '@/types/agent';

type Props = {
    columns: string[];
    rows: CellValue[][];
};

const CHART_TYPES: Record<ChartType, string> = {
    bar: 'ai.artifacts.chart_types.bar',
    line: 'ai.artifacts.chart_types.line',
    area: 'ai.artifacts.chart_types.area',
    pie: 'ai.artifacts.chart_types.pie',
};

/**
 * Beyond this many points, the chart is no longer readable: only the first ones are drawn.
 */
const MAX_POINTS = 500;

/**
 * A column whose loaded values are all numbers, or NULL: a series of the chart.
 */
const isNumeric = (rows: CellValue[][], index: number) =>
    rows.some((row) => row[index] !== null) &&
    rows.every(
        (row) =>
            row[index] === null ||
            typeof row[index] === 'number' ||
            (typeof row[index] === 'string' &&
                (row[index] as string).trim() !== '' &&
                !Number.isNaN(Number(row[index]))),
    );

/**
 * The loaded rows of a result drawn as a chart, as the chat draws its own: the user chooses the kind of chart, the
 * column on the X axis and the series. By default, the first column that is not numeric gives the axis, the numeric
 * ones the series. Loaded apart from the console: the charts are only needed once asked for.
 */
export default function ResultChart({ columns, rows }: Props) {
    const { t } = useLaravelReactI18n();
    const numeric = columns.filter((_, index) => isNumeric(rows, index));
    const [chartType, setChartType] = useState<ChartType>('bar');
    const [xColumn, setXColumn] = useState(
        () => columns.find((column) => !numeric.includes(column)) ?? columns[0],
    );
    // Identifiers are numbers too, but drawing them says nothing.
    const [yColumns, setYColumns] = useState(() =>
        numeric
            .filter((column) => column !== xColumn && !/(^|_)id$/i.test(column))
            .slice(0, 4),
    );
    const [stacked, setStacked] = useState(false);

    const points = rows
        .slice(0, MAX_POINTS)
        .map((row) =>
            Object.fromEntries(
                columns.map((column, index) => [column, row[index]]),
            ),
        );
    // A pie has a single series.
    const series = chartType === 'pie' ? yColumns.slice(0, 1) : yColumns;

    return (
        <div className="flex min-h-0 flex-1 flex-col">
            <div className="flex flex-wrap items-center gap-2 border-b px-3 py-1.5 text-xs">
                <NativeSelect
                    size="sm"
                    value={chartType}
                    onChange={(event) =>
                        setChartType(event.target.value as ChartType)
                    }
                    aria-label={t('tables.chart.type')}
                >
                    {(Object.keys(CHART_TYPES) as ChartType[]).map((type) => (
                        <NativeSelectOption key={type} value={type}>
                            {t(CHART_TYPES[type])}
                        </NativeSelectOption>
                    ))}
                </NativeSelect>
                <span className="text-muted-foreground">{t('tables.chart.axis')}</span>
                <NativeSelect
                    size="sm"
                    value={xColumn}
                    onChange={(event) => setXColumn(event.target.value)}
                    aria-label={t('tables.chart.axis_column')}
                >
                    {columns.map((column) => (
                        <NativeSelectOption key={column} value={column}>
                            {column}
                        </NativeSelectOption>
                    ))}
                </NativeSelect>
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button variant="outline" size="sm" className="h-8">
                            {series.length === 0
                                ? t('tables.chart.choose_series')
                                : series.join(', ')}
                            <ChevronDownIcon />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="start" className="w-56">
                        <DropdownMenuLabel>
                            {t('tables.chart.series')}
                        </DropdownMenuLabel>
                        {numeric.length === 0 && (
                            <p className="px-2 py-1.5 text-xs text-muted-foreground">
                                {t('tables.chart.no_numeric')}
                            </p>
                        )}
                        {numeric.map((column) => (
                            <DropdownMenuCheckboxItem
                                key={column}
                                checked={yColumns.includes(column)}
                                onSelect={(event) => event.preventDefault()}
                                onCheckedChange={(checked) =>
                                    setYColumns(
                                        checked
                                            ? [...yColumns, column]
                                            : yColumns.filter(
                                                  (item) => item !== column,
                                              ),
                                    )
                                }
                            >
                                {column}
                            </DropdownMenuCheckboxItem>
                        ))}
                    </DropdownMenuContent>
                </DropdownMenu>
                {(chartType === 'bar' || chartType === 'area') &&
                    series.length > 1 && (
                        <label className="flex items-center gap-1.5 text-muted-foreground">
                            <Switch
                                size="sm"
                                checked={stacked}
                                onCheckedChange={setStacked}
                            />
                            {t('tables.chart.stacked')}
                        </label>
                    )}
                {rows.length > MAX_POINTS && (
                    <span className="ml-auto text-muted-foreground">
                        {t('tables.chart.first_points', { count: MAX_POINTS, total: rows.length })}
                    </span>
                )}
            </div>
            <div className="min-h-0 flex-1 overflow-auto p-3">
                {series.length === 0 ? (
                    <p className="p-6 text-center text-sm text-muted-foreground">
                        {t('tables.chart.choose_one')}
                    </p>
                ) : (
                    <ChartView
                        chartType={chartType}
                        xColumn={xColumn}
                        yColumns={series}
                        stacked={stacked}
                        rows={points}
                        className="h-full max-h-none min-h-64"
                    />
                )}
            </div>
        </div>
    );
}
