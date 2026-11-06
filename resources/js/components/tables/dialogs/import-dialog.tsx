import { FileIcon, UploadIcon } from 'lucide-react';
import { useEffect, useState } from 'react';
import Alert from '@/components/alert';
import Button from '@/components/form/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
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
        label: 'Tout ou rien',
        description:
            "Si une instruction échoue, aucune n'est gardée. MySQL et MariaDB valident toutefois chaque changement de structure aussitôt.",
    },
    {
        value: 'stop',
        label: "S'arrêter à la première erreur",
        description: 'Les instructions précédentes sont gardées.',
    },
    {
        value: 'continue',
        label: 'Continuer malgré les erreurs',
        description: 'Chaque instruction est tentée, les échecs sont listés.',
    },
];

const PREVIEW_BYTES = 64 * 1024;

const plural = (count: number, word: string) =>
    `${count} ${word}${count > 1 ? 's' : ''}`;

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
                    {file ? file.name : 'Choisir ou déposer un fichier'}
                </span>
                <span className="block text-xs text-muted-foreground">
                    {file
                        ? `${(file.size / 1024).toLocaleString('fr-FR', { maximumFractionDigits: 1 })} Ko`
                        : hint}
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
    const [tab, setTab] = useState<'sql' | 'csv'>(table ? 'csv' : 'sql');
    const [processing, setProcessing] = useState(false);

    return (
        <Dialog open onOpenChange={(open) => !open && !processing && onClose()}>
            <DialogContent className="flex max-h-[90vh] flex-col sm:max-w-3xl">
                <DialogHeader>
                    <DialogTitle>Importer</DialogTitle>
                    <DialogDescription>
                        Exécutez un script SQL dans la base, ou ajoutez les
                        lignes d'un fichier CSV à une table.
                    </DialogDescription>
                </DialogHeader>
                <Tabs
                    value={tab}
                    onValueChange={(value) => setTab(value as 'sql' | 'csv')}
                    className="flex min-h-0 flex-1 flex-col"
                >
                    <TabsList>
                        <TabsTrigger value="sql" disabled={processing}>
                            Script SQL
                        </TabsTrigger>
                        <TabsTrigger value="csv" disabled={processing}>
                            Fichier CSV
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
            </DialogContent>
        </Dialog>
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
        <div className="flex min-h-0 flex-1 flex-col gap-4 pt-2">
            <div className="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto">
                <FileField
                    file={file}
                    accept=".sql,.gz,.zip,.txt"
                    hint=".sql, .sql.gz ou .zip"
                    onChange={(next) => {
                        setFile(next);
                        setReport(null);
                    }}
                />
                {!file && (
                    <Textarea
                        value={sql}
                        onChange={(event) => setSql(event.target.value)}
                        placeholder="Ou collez le script ici"
                        spellCheck={false}
                        className="min-h-32 font-mono text-xs"
                        aria-label="Script SQL"
                    />
                )}
                {file && (
                    <Button
                        variant="ghost"
                        size="sm"
                        className="self-start"
                        onClick={() => setFile(null)}
                    >
                        Saisir le script à la place
                    </Button>
                )}

                <fieldset>
                    <legend className="mb-2 text-sm font-medium">
                        En cas d'erreur
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
                                    <span className="block">{item.label}</span>
                                    <span className="block text-xs text-muted-foreground">
                                        {item.description}
                                    </span>
                                </span>
                            </Label>
                        ))}
                    </RadioGroup>
                </fieldset>

                {error && (
                    <Alert
                        variant="destructive"
                        title="L'import a échoué"
                        message={error}
                    />
                )}

                {report && (
                    <Alert
                        variant={report.failed > 0 ? 'destructive' : 'success'}
                        title={
                            report.kept
                                ? `${plural(report.executed, 'instruction')} exécutée${report.executed > 1 ? 's' : ''} sur ${report.statements}, en ${report.duration} ms`
                                : "Rien n'a été gardé : l'import a été annulé"
                        }
                        message={
                            report.skipped > 0
                                ? `${plural(report.skipped, 'instruction')} de transaction du script ignorée${report.skipped > 1 ? 's' : ''} : l'import a la sienne.`
                                : report.failed > 0
                                  ? 'La base a refusé les instructions suivantes.'
                                  : 'Le script a été exécuté entièrement.'
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
                                            Instruction {failure.index} :{' '}
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

            <DialogFooter>
                <Button
                    variant="outline"
                    onClick={onClose}
                    disabled={processing}
                >
                    {report ? 'Fermer' : 'Annuler'}
                </Button>
                <Button
                    onClick={run}
                    disabled={processing || (!file && sql.trim() === '')}
                    icon={UploadIcon}
                    processing={processing}
                >
                    Exécuter le script
                </Button>
            </DialogFooter>
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
        <div className="flex min-h-0 flex-1 flex-col gap-4 pt-2">
            <div className="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto">
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="flex flex-col gap-2">
                        <Label htmlFor="csv-table">Table</Label>
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
                        <Label htmlFor="csv-delimiter">Séparateur</Label>
                        <NativeSelect
                            id="csv-delimiter"
                            className="w-full"
                            value={delimiter}
                            onChange={(event) =>
                                setDelimiter(event.target.value as CsvDelimiter)
                            }
                        >
                            <NativeSelectOption value=",">
                                Virgule ,
                            </NativeSelectOption>
                            <NativeSelectOption value=";">
                                Point-virgule ;
                            </NativeSelectOption>
                            <NativeSelectOption value="tab">
                                Tabulation
                            </NativeSelectOption>
                            <NativeSelectOption value="|">
                                Barre |
                            </NativeSelectOption>
                        </NativeSelect>
                    </div>
                </div>

                <FileField
                    file={file}
                    accept=".csv,.tsv,.txt,.gz,.zip"
                    hint=".csv, .csv.gz ou .zip"
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
                        La première ligne nomme les colonnes
                    </Label>
                    <Label className="flex items-center gap-2 font-normal">
                        <Checkbox
                            checked={emptyAsNull}
                            onCheckedChange={(checked) =>
                                setEmptyAsNull(checked === true)
                            }
                        />
                        Valeur vide = NULL
                    </Label>
                    <Label className="flex items-center gap-2 font-normal text-destructive">
                        <Checkbox
                            checked={replace}
                            onCheckedChange={(checked) =>
                                setReplace(checked === true)
                            }
                        />
                        Supprimer d'abord les lignes de la table
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
                                                        `Colonne ${index + 1}`}
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
                                                    aria-label={`Colonne de la table pour ${headings[index] || `la colonne ${index + 1}`}`}
                                                >
                                                    <NativeSelectOption value="">
                                                        Ignorer
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
                        Un fichier compressé n'a pas d'aperçu : ses colonnes
                        vont, dans l'ordre, aux colonnes de la table.
                    </p>
                )}

                {error && (
                    <Alert
                        variant="destructive"
                        title="L'import a échoué"
                        message={error}
                    />
                )}

                {report && (
                    <Alert
                        variant="success"
                        title={`${plural(report.inserted, 'ligne')} importée${report.inserted > 1 ? 's' : ''}`}
                        message={`${report.deleted > 0 ? `${plural(report.deleted, 'ligne')} supprimée${report.deleted > 1 ? 's' : ''} avant l'import, ` : ''}en ${report.duration} ms.`}
                    />
                )}
            </div>

            <DialogFooter>
                <Button
                    variant="outline"
                    onClick={onClose}
                    disabled={processing}
                >
                    {report ? 'Fermer' : 'Annuler'}
                </Button>
                <Button
                    onClick={run}
                    disabled={
                        processing || !file || (text !== '' && mapped === 0)
                    }
                    icon={UploadIcon}
                    processing={processing}
                >
                    Importer dans {table}
                </Button>
            </DialogFooter>
        </div>
    );
}
