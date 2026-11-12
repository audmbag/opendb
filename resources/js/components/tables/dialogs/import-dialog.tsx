import { useLaravelReactI18n } from 'laravel-react-i18n';
import { FileIcon, UploadIcon } from 'lucide-react';
import { useEffect, useState } from 'react';
import { formatBytes } from '@/components/ai/artifacts/format';
import Alert from '@/components/alert';
import Button from '@/components/form/button';
import Modal from '@/components/modals/modal';
import ModalFooter from '@/components/modals/modal-footer';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import {
    NativeSelect,
    NativeSelectOption,
} from '@/components/ui/native-select';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { uppercaseSqlKeywords } from '@/lib/sql';
import { cn } from '@/lib/utils';
import DatabaseImportController from '@/wayfinder/actions/App/Http/Controllers/Database/DatabaseImportController';
import TableBrowserController from '@/wayfinder/actions/App/Http/Controllers/Tables/TableBrowserController';
import TableImportController from '@/wayfinder/actions/App/Http/Controllers/Tables/TableImportController';
import type { TableRequestError } from '../api';
import { requestTableData } from '../api';
import type { CsvDelimiter } from '../csv';
import { guessDelimiter, parseCsv } from '../csv';
import type {
    CsvImportReport,
    ImportMode,
    SqlImportReport,
    TableOutline,
    TableSummary,
} from '../types';

type Props = {
    projectId: string;
    tables: TableSummary[];
    /**
     * Opens the CSV import into this table instead of the SQL import.
     */
    table?: string | null;
    onImported: (tables: string[] | null) => void;
    onClose: () => void;
};

const MODES: { value: ImportMode; label: string; description: string }[] = [
    {
        value: 'transaction',
        label: 'tables.import.modes.transaction',
        description: 'tables.import.modes.transaction_description',
    },
    {
        value: 'stop',
        label: 'tables.import.modes.stop',
        description: 'tables.import.modes.stop_description',
    },
    {
        value: 'continue',
        label: 'tables.import.modes.continue',
        description: 'tables.import.modes.continue_description',
    },
];

const PREVIEW_BYTES = 64 * 1024;

function FileField({
    file,
    accept,
    hint,
    onChange,
}: {
    file: File | null;
    accept: string;
    hint: string;
    onChange: (file: File | null) => void;
}) {
    const { t } = useLaravelReactI18n();
    const [dragging, setDragging] = useState(false);

    return (
        <label
            onDragOver={(event) => {
                event.preventDefault();
                setDragging(true);
            }}
            onDragLeave={() => setDragging(false)}
            onDrop={(event) => {
                event.preventDefault();
                setDragging(false);
                onChange(event.dataTransfer.files[0] ?? null);
            }}
            className={cn(
                'flex cursor-pointer items-center gap-3 rounded-md border border-dashed px-4 py-3 text-sm transition-colors hover:bg-muted/50',
                dragging && 'border-ring bg-muted/50',
            )}
        >
            {file ? (
                <FileIcon className="size-5 shrink-0 text-muted-foreground" />
            ) : (
                <UploadIcon className="size-5 shrink-0 text-muted-foreground" />
            )}
            <span className="min-w-0 flex-1">
                <span className="block truncate font-medium">
                    {file ? file.name : t('tables.import.choose_file')}
                </span>
                <span className="block text-xs text-muted-foreground">
                    {file ? formatBytes(file.size) : hint}
                </span>
            </span>
            <input
                type="file"
                accept={accept}
                className="sr-only"
                onChange={(event) => onChange(event.target.files?.[0] ?? null)}
            />
        </label>
    );
}

/**
 * The import into the database: a SQL script, sent as a file or typed, or the lines of a CSV file added to a table.
 */
export default function ImportDialog({
    projectId,
    tables,
    table,
    onImported,
    onClose,
}: Props) {
    const { t } = useLaravelReactI18n();
    const [tab, setTab] = useState<'sql' | 'csv'>(table ? 'csv' : 'sql');
    const [processing, setProcessing] = useState(false);

    return (
        <Modal
            open
            showHeader
            className="h-[min(90vh,48rem)] sm:max-w-3xl"
            onOpenChange={(open) => !open && !processing && onClose()}
            icon={UploadIcon}
            title={t('tables.import.title')}
            description={t('tables.import.description')}
        >
            <Tabs
                value={tab}
                onValueChange={(value) => setTab(value as 'sql' | 'csv')}
                className="flex h-full flex-col gap-0"
            >
                <TabsList className="mx-4 mt-4">
                    <TabsTrigger value="sql" disabled={processing}>
                        {t('tables.import.sql_script')}
                    </TabsTrigger>
                    <TabsTrigger value="csv" disabled={processing}>
                        {t('tables.import.csv_file')}
                    </TabsTrigger>
                </TabsList>
                <TabsContent
                    value="sql"
                    className="flex min-h-0 flex-1 flex-col"
                >
                    <SqlImport
                        projectId={projectId}
                        processing={processing}
                        onProcessing={setProcessing}
                        onImported={() => onImported(null)}
                        onClose={onClose}
                    />
                </TabsContent>
                <TabsContent
                    value="csv"
                    className="flex min-h-0 flex-1 flex-col"
                >
                    <CsvImport
                        projectId={projectId}
                        tables={tables}
                        initialTable={table ?? tables[0]?.name ?? ''}
                        processing={processing}
                        onProcessing={setProcessing}
                        onImported={(name) => onImported([name])}
                        onClose={onClose}
                    />
                </TabsContent>
            </Tabs>
        </Modal>
    );
}

type ImportStepProps = {
    projectId: string;
    processing: boolean;
    onProcessing: (processing: boolean) => void;
    onClose: () => void;
};

function SqlImport({
    projectId,
    processing,
    onProcessing,
    onImported,
    onClose,
}: ImportStepProps & { onImported: () => void }) {
    const { t, tChoice } = useLaravelReactI18n();
    const [file, setFile] = useState<File | null>(null);
    const [sql, setSql] = useState('');
    const [mode, setMode] = useState<ImportMode>('transaction');
    const [report, setReport] = useState<SqlImportReport | null>(null);
    const [error, setError] = useState<string | null>(null);

    const run = async () => {
        onProcessing(true);
        setError(null);
        setReport(null);

        const data = new FormData();
        data.append('mode', mode);

        if (file) {
            data.append('file', file);
        } else {
            data.append('sql', sql);
        }

        try {
            const result = await requestTableData<SqlImportReport>(
                'post',
                DatabaseImportController.url({ project: projectId }),
                { data },
            );

            setReport(result);

            if (result.executed > 0) {
                onImported();
            }
        } catch (exception) {
            setError((exception as TableRequestError).first());
        } finally {
            onProcessing(false);
        }
    };

    return (
        <div className="flex min-h-0 flex-1 flex-col">
            <div className="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto p-4">
                <FileField
                    file={file}
                    accept=".sql,.gz,.zip,.txt"
                    hint={t('tables.import.sql_hint')}
                    onChange={(next) => {
                        setFile(next);
                        setReport(null);
                    }}
                />
                {!file && (
                    <Textarea
                        value={sql}
                        onChange={(event) => setSql(event.target.value)}
                        placeholder={t('tables.import.paste_script')}
                        spellCheck={false}
                        className="min-h-32 font-mono text-xs"
                        aria-label={t('tables.import.sql_script')}
                    />
                )}
                {file && (
                    <Button
                        variant="ghost"
                        size="sm"
                        className="self-start"
                        onClick={() => setFile(null)}
                    >
                        {t('tables.import.type_script')}
                    </Button>
                )}

                <fieldset>
                    <legend className="mb-2 text-sm font-medium">
                        {t('tables.import.on_error')}
                    </legend>
                    <RadioGroup
                        value={mode}
                        onValueChange={(value) => setMode(value as ImportMode)}
                    >
                        {MODES.map((item) => (
                            <Label
                                key={item.value}
                                className="flex items-start gap-2 font-normal"
                            >
                                <RadioGroupItem
                                    value={item.value}
                                    className="mt-0.5"
                                />
                                <span>
                                    <span className="block">
                                        {t(item.label)}
                                    </span>
                                    <span className="block text-xs text-muted-foreground">
                                        {t(item.description)}
                                    </span>
                                </span>
                            </Label>
                        ))}
                    </RadioGroup>
                </fieldset>

                {error && (
                    <Alert
                        variant="destructive"
                        title={t('tables.import.failed')}
                        message={error}
                    />
                )}

                {report && (
                    <Alert
                        variant={report.failed > 0 ? 'destructive' : 'success'}
                        title={
                            report.kept
                                ? tChoice(
                                      'tables.import.sql_report',
                                      report.executed,
                                      {
                                          total: report.statements,
                                          duration: report.duration,
                                      },
                                  )
                                : t('tables.import.nothing_kept')
                        }
                        message={
                            report.skipped > 0
                                ? tChoice(
                                      'tables.import.skipped_transactions',
                                      report.skipped,
                                  )
                                : report.failed > 0
                                  ? t('tables.import.refused')
                                  : t('tables.import.completed')
                        }
                    >
                        {report.failures.length > 0 && (
                            <ul className="flex max-h-48 flex-col gap-2 overflow-y-auto text-sm">
                                {report.failures.map((failure) => (
                                    <li
                                        key={failure.index}
                                        className="flex min-w-0 flex-col gap-0.5"
                                    >
                                        <span>
                                            {t(
                                                'tables.import.statement_failure',
                                                { index: failure.index },
                                            )}{' '}
                                            {failure.message}
                                        </span>
                                        <code className="truncate font-mono text-xs opacity-80">
                                            {uppercaseSqlKeywords(
                                                failure.statement,
                                            )}
                                        </code>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Alert>
                )}
            </div>

            <ModalFooter>
                <Button
                    variant="outline"
                    onClick={onClose}
                    disabled={processing}
                >
                    {report ? t('common.close') : t('common.cancel')}
                </Button>
                <Button
                    onClick={run}
                    disabled={processing || (!file && sql.trim() === '')}
                    icon={UploadIcon}
                    processing={processing}
                >
                    {t('tables.import.run_script')}
                </Button>
            </ModalFooter>
        </div>
    );
}

function CsvImport({
    projectId,
    tables,
    initialTable,
    processing,
    onProcessing,
    onImported,
    onClose,
}: ImportStepProps & {
    tables: TableSummary[];
    initialTable: string;
    onImported: (table: string) => void;
}) {
    const { t, tChoice } = useLaravelReactI18n();
    const [table, setTable] = useState(initialTable);
    const [columns, setColumns] = useState<string[] | null>(null);
    const [file, setFile] = useState<File | null>(null);
    const [text, setText] = useState('');
    const [delimiter, setDelimiter] = useState<CsvDelimiter>(',');
    const [header, setHeader] = useState(true);
    const [emptyAsNull, setEmptyAsNull] = useState(true);
    const [replace, setReplace] = useState(false);
    const [chosenMapping, setChosenMapping] = useState<{
        key: string;
        columns: string[];
    } | null>(null);
    const [report, setReport] = useState<CsvImportReport | null>(null);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        const controller = new AbortController();

        requestTableData<TableOutline>(
            'get',
            TableBrowserController.outline.url({
                project: projectId,
                table: encodeURIComponent(table),
            }),
            { signal: controller.signal },
        )
            .then((outline) =>
                setColumns(outline.columns.map((column) => column.name)),
            )
            .catch(() => null);

        return () => controller.abort();
    }, [projectId, table]);

    const lines = text ? parseCsv(text, delimiter, 6) : [];
    const width = Math.max(0, ...lines.map((line) => line.length));
    const headings = header && lines[0] ? lines[0] : [];
    const preview = header ? lines.slice(1) : lines;

    // Each column of the file goes to the column of the table with the same name, until the user chooses another.
    const mappingKey = `${table}|${delimiter}|${header}|${text.length}|${text.slice(0, 200)}`;
    const mapping =
        chosenMapping?.key === mappingKey
            ? chosenMapping.columns
            : Array.from({ length: width }, (_, index) => {
                  const heading = (headings[index] ?? '').trim().toLowerCase();

                  return (
                      columns?.find(
                          (column) => column.toLowerCase() === heading,
                      ) ?? (header ? '' : (columns?.[index] ?? ''))
                  );
              });
    const setMapping = (update: (current: string[]) => string[]) =>
        setChosenMapping({ key: mappingKey, columns: update(mapping) });

    const chooseFile = async (next: File | null) => {
        setFile(next);
        setReport(null);
        setError(null);

        if (!next) {
            setText('');

            return;
        }

        if (/\.(gz|zip)$/i.test(next.name)) {
            setText('');

            return;
        }

        const beginning = await next.slice(0, PREVIEW_BYTES).text();
        setText(beginning.replace(/^\uFEFF/, ''));
        setDelimiter(guessDelimiter(beginning));
    };

    const run = async () => {
        if (!file) {
            return;
        }

        onProcessing(true);
        setError(null);
        setReport(null);

        const data = new FormData();
        data.append('file', file);
        data.append('delimiter', delimiter);
        data.append('header', header ? '1' : '0');
        data.append('emptyAsNull', emptyAsNull ? '1' : '0');
        data.append('replace', replace ? '1' : '0');
        mapping.forEach((column, index) =>
            data.append(`mapping[${index}]`, column),
        );

        try {
            const result = await requestTableData<CsvImportReport>(
                'post',
                TableImportController.url({
                    project: projectId,
                    table: encodeURIComponent(table),
                }),
                { data },
            );

            setReport(result);
            onImported(table);
        } catch (exception) {
            setError((exception as TableRequestError).first());
        } finally {
            onProcessing(false);
        }
    };

    const mapped = mapping.filter(Boolean).length;

    return (
        <div className="flex min-h-0 flex-1 flex-col">
            <div className="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto p-4">
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="flex flex-col gap-2">
                        <Label htmlFor="csv-table">
                            {t('tables.import.table')}
                        </Label>
                        <NativeSelect
                            id="csv-table"
                            className="w-full"
                            value={table}
                            onChange={(event) => {
                                setTable(event.target.value);
                                setColumns(null);
                                setReport(null);
                            }}
                        >
                            {tables.map((item) => (
                                <NativeSelectOption
                                    key={item.name}
                                    value={item.name}
                                >
                                    {item.name}
                                </NativeSelectOption>
                            ))}
                        </NativeSelect>
                    </div>
                    <div className="flex flex-col gap-2">
                        <Label htmlFor="csv-delimiter">
                            {t('tables.import.delimiter')}
                        </Label>
                        <NativeSelect
                            id="csv-delimiter"
                            className="w-full"
                            value={delimiter}
                            onChange={(event) =>
                                setDelimiter(event.target.value as CsvDelimiter)
                            }
                        >
                            <NativeSelectOption value=",">
                                {t('tables.import.delimiters.comma')}
                            </NativeSelectOption>
                            <NativeSelectOption value=";">
                                {t('tables.import.delimiters.semicolon')}
                            </NativeSelectOption>
                            <NativeSelectOption value="tab">
                                {t('tables.import.delimiters.tab')}
                            </NativeSelectOption>
                            <NativeSelectOption value="|">
                                {t('tables.import.delimiters.pipe')}
                            </NativeSelectOption>
                        </NativeSelect>
                    </div>
                </div>

                <FileField
                    file={file}
                    accept=".csv,.tsv,.txt,.gz,.zip"
                    hint={t('tables.import.csv_hint')}
                    onChange={chooseFile}
                />

                <div className="flex flex-wrap gap-x-6 gap-y-2">
                    <Label className="flex items-center gap-2 font-normal">
                        <Checkbox
                            checked={header}
                            onCheckedChange={(checked) =>
                                setHeader(checked === true)
                            }
                        />
                        {t('tables.import.header')}
                    </Label>
                    <Label className="flex items-center gap-2 font-normal">
                        <Checkbox
                            checked={emptyAsNull}
                            onCheckedChange={(checked) =>
                                setEmptyAsNull(checked === true)
                            }
                        />
                        {t('tables.import.empty_as_null')}
                    </Label>
                    <Label className="flex items-center gap-2 font-normal text-destructive">
                        <Checkbox
                            checked={replace}
                            onCheckedChange={(checked) =>
                                setReplace(checked === true)
                            }
                        />
                        {t('tables.import.replace')}
                    </Label>
                </div>

                {lines.length > 0 && columns && (
                    <div className="overflow-x-auto rounded-md border">
                        <table className="w-full text-xs">
                            <thead className="bg-muted text-left">
                                <tr>
                                    {Array.from(
                                        { length: width },
                                        (_, index) => (
                                            <th
                                                key={index}
                                                className="min-w-36 px-2 py-1.5 font-normal"
                                            >
                                                <span className="mb-1 block truncate font-medium">
                                                    {headings[index] ||
                                                        t(
                                                            'tables.import.column_number',
                                                            {
                                                                number:
                                                                    index + 1,
                                                            },
                                                        )}
                                                </span>
                                                <NativeSelect
                                                    size="sm"
                                                    className="w-full"
                                                    value={mapping[index] ?? ''}
                                                    onChange={(event) =>
                                                        setMapping((current) =>
                                                            current.map(
                                                                (
                                                                    column,
                                                                    position,
                                                                ) =>
                                                                    position ===
                                                                    index
                                                                        ? event
                                                                              .target
                                                                              .value
                                                                        : column,
                                                            ),
                                                        )
                                                    }
                                                    aria-label={t(
                                                        'tables.import.column_for',
                                                        {
                                                            column:
                                                                headings[
                                                                    index
                                                                ] ||
                                                                t(
                                                                    'tables.import.column_number',
                                                                    {
                                                                        number:
                                                                            index +
                                                                            1,
                                                                    },
                                                                ),
                                                        },
                                                    )}
                                                >
                                                    <NativeSelectOption value="">
                                                        {t(
                                                            'tables.import.ignore',
                                                        )}
                                                    </NativeSelectOption>
                                                    {columns.map((column) => (
                                                        <NativeSelectOption
                                                            key={column}
                                                            value={column}
                                                        >
                                                            → {column}
                                                        </NativeSelectOption>
                                                    ))}
                                                </NativeSelect>
                                            </th>
                                        ),
                                    )}
                                </tr>
                            </thead>
                            <tbody>
                                {preview.map((line, row) => (
                                    <tr key={row} className="border-t">
                                        {Array.from(
                                            { length: width },
                                            (_, index) => (
                                                <td
                                                    key={index}
                                                    className={cn(
                                                        'max-w-48 truncate px-2 py-1',
                                                        !mapping[index] &&
                                                            'text-muted-foreground/60',
                                                    )}
                                                >
                                                    {line[index] ?? ''}
                                                </td>
                                            ),
                                        )}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                {file && !text && (
                    <p className="text-sm text-muted-foreground">
                        {t('tables.import.compressed_no_preview')}
                    </p>
                )}

                {error && (
                    <Alert
                        variant="destructive"
                        title={t('tables.import.failed')}
                        message={error}
                    />
                )}

                {report && (
                    <Alert
                        variant="success"
                        title={tChoice(
                            'tables.import.rows_imported',
                            report.inserted,
                        )}
                        message={
                            report.deleted > 0
                                ? tChoice(
                                      'tables.import.rows_deleted',
                                      report.deleted,
                                      { duration: report.duration },
                                  )
                                : t('tables.import.duration', {
                                      duration: report.duration,
                                  })
                        }
                    />
                )}
            </div>

            <ModalFooter>
                <Button
                    variant="outline"
                    onClick={onClose}
                    disabled={processing}
                >
                    {report ? t('common.close') : t('common.cancel')}
                </Button>
                <Button
                    onClick={run}
                    disabled={
                        processing || !file || (text !== '' && mapped === 0)
                    }
                    icon={UploadIcon}
                    processing={processing}
                >
                    {t('tables.import.import_into', { table })}
                </Button>
            </ModalFooter>
        </div>
    );
}
