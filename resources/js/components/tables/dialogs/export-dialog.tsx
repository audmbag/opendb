import { usePage } from '@inertiajs/react';
import { DownloadIcon, SearchIcon } from 'lucide-react';
import { useDeferredValue, useState } from 'react';
import { toast } from 'sonner';
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
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { cn } from '@/lib/utils';
import DatabaseExportController from '@/wayfinder/actions/App/Http/Controllers/Database/DatabaseExportController';
import type { TableRequestError } from '../api';
import { downloadFile } from '../download';
import type { TableSummary } from '../types';

type Props = {
    projectId: string;
    tables: TableSummary[];
    /**
     * The tables checked when the dialog opens; every table when there are none.
     */
    initialTables?: string[];
    onClose: () => void;
};

type Content = { schema: boolean; data: boolean };

type Format = 'sql' | 'csv';

type Compression = 'none' | 'zip' | 'gzip' | 'tar';

const COMPRESSIONS: { value: Compression; label: string; hint: string }[] = [
    { value: 'none', label: 'Aucune', hint: 'le fichier tel quel' },
    { value: 'zip', label: 'Zip', hint: '.zip' },
    { value: 'gzip', label: 'Gzip', hint: '.gz' },
    { value: 'tar', label: 'Tar', hint: '.tar' },
];

/**
 * The export of the database: the tables to keep, their structure, their rows or both, in a SQL script that
 * recreates them or in a CSV file per table.
 */
export default function ExportDialog({
    projectId,
    tables,
    initialTables,
    onClose,
}: Props) {
    const [contents, setContents] = useState<Record<string, Content>>(() =>
        Object.fromEntries(
            (initialTables?.length
                ? initialTables
                : tables.map((table) => table.name)
            ).map((name) => [name, { schema: true, data: true }]),
        ),
    );
    const exportSettings = usePage().props.auth.user.settings.export;
    const [format, setFormat] = useState<Format>(exportSettings.format);
    const [compression, setCompression] = useState<Compression>(
        exportSettings.compression,
    );
    const [search, setSearch] = useState('');
    const [processing, setProcessing] = useState(false);
    const deferredSearch = useDeferredValue(search.trim().toLowerCase());

    const visible = tables.filter((table) =>
        table.name.toLowerCase().includes(deferredSearch),
    );
    const chosen = Object.entries(contents).filter(
        ([, content]) => content.data || (format === 'sql' && content.schema),
    );

    const setContent = (name: string, content: Content | null) =>
        setContents((current) => {
            const next = { ...current };

            if (content && (content.schema || content.data)) {
                next[name] = content;
            } else {
                delete next[name];
            }

            return next;
        });

    const setAll = (content: Content | null) =>
        setContents((current) => {
            const next = { ...current };

            for (const table of visible) {
                if (content) {
                    next[table.name] = content;
                } else {
                    delete next[table.name];
                }
            }

            return next;
        });

    const download = async () => {
        setProcessing(true);

        try {
            const name = await downloadFile(
                DatabaseExportController.url({ project: projectId }),
                {
                    tables: chosen.map(([table, content]) => ({
                        name: table,
                        schema: format === 'sql' && content.schema,
                        data: content.data,
                    })),
                    format,
                    compression,
                },
            );

            toast.success('Export téléchargé', { description: name });
            onClose();
        } catch (exception) {
            toast.error("L'export a échoué", {
                description: (exception as TableRequestError).first(),
            });
        } finally {
            setProcessing(false);
        }
    };

    return (
        <Dialog open onOpenChange={(open) => !open && !processing && onClose()}>
            <DialogContent className="flex max-h-[90vh] flex-col sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Exporter la base</DialogTitle>
                    <DialogDescription>
                        Le script SQL recrée les tables puis leurs lignes, clés
                        étrangères désactivées le temps de l'import. Le CSV
                        donne un fichier de lignes par table.
                    </DialogDescription>
                </DialogHeader>

                <div className="grid gap-4 sm:grid-cols-2">
                    <fieldset className="flex flex-col gap-2">
                        <legend className="mb-2 text-sm font-medium">
                            Format
                        </legend>
                        <RadioGroup
                            value={format}
                            onValueChange={(value) =>
                                setFormat(value as Format)
                            }
                        >
                            <Label className="flex items-center gap-2 font-normal">
                                <RadioGroupItem value="sql" />
                                Script SQL
                            </Label>
                            <Label className="flex items-center gap-2 font-normal">
                                <RadioGroupItem value="csv" />
                                CSV, un fichier par table
                            </Label>
                        </RadioGroup>
                    </fieldset>
                    <fieldset className="flex flex-col gap-2">
                        <legend className="mb-2 text-sm font-medium">
                            Compression
                        </legend>
                        <RadioGroup
                            value={compression}
                            onValueChange={(value) =>
                                setCompression(value as Compression)
                            }
                            className="grid-cols-2"
                        >
                            {COMPRESSIONS.map((item) => (
                                <Label
                                    key={item.value}
                                    className="flex items-center gap-2 font-normal"
                                    title={item.hint}
                                >
                                    <RadioGroupItem value={item.value} />
                                    {item.label}
                                </Label>
                            ))}
                        </RadioGroup>
                        {compression === 'none' && format === 'csv' && (
                            <p className="text-xs text-muted-foreground">
                                Plusieurs tables en CSV sont regroupées dans un
                                zip.
                            </p>
                        )}
                    </fieldset>
                </div>

                <div className="flex min-h-0 flex-1 flex-col gap-2">
                    <div className="flex flex-wrap items-center gap-2">
                        <label className="relative flex min-w-48 flex-1 items-center">
                            <span className="sr-only">Filtrer les tables</span>
                            <SearchIcon className="pointer-events-none absolute left-2.5 size-4 text-muted-foreground" />
                            <input
                                type="search"
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                placeholder="Filtrer les tables"
                                className="h-8 w-full rounded-md border border-input bg-transparent pr-2 pl-8 text-sm outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 dark:bg-input/30"
                            />
                        </label>
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => setAll({ schema: true, data: true })}
                        >
                            Tout cocher
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => setAll(null)}
                        >
                            Tout décocher
                        </Button>
                    </div>

                    <div className="min-h-40 flex-1 overflow-y-auto rounded-md border">
                        <table className="w-full text-sm">
                            <thead className="sticky top-0 bg-muted text-left text-xs text-muted-foreground">
                                <tr>
                                    <th className="px-3 py-1.5 font-medium">
                                        Table
                                    </th>
                                    <th
                                        className={cn(
                                            'w-24 px-3 py-1.5 text-center font-medium',
                                            format === 'csv' && 'opacity-50',
                                        )}
                                    >
                                        Structure
                                    </th>
                                    <th className="w-24 px-3 py-1.5 text-center font-medium">
                                        Données
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {visible.map((table) => {
                                    const content = contents[table.name] ?? {
                                        schema: false,
                                        data: false,
                                    };

                                    return (
                                        <tr
                                            key={table.name}
                                            className="border-t"
                                        >
                                            <td className="max-w-0 truncate px-3 py-1.5">
                                                {table.name}
                                            </td>
                                            <td className="px-3 py-1.5 text-center">
                                                <Checkbox
                                                    checked={content.schema}
                                                    disabled={format === 'csv'}
                                                    onCheckedChange={(
                                                        checked,
                                                    ) =>
                                                        setContent(table.name, {
                                                            ...content,
                                                            schema:
                                                                checked ===
                                                                true,
                                                        })
                                                    }
                                                    aria-label={`Structure de ${table.name}`}
                                                />
                                            </td>
                                            <td className="px-3 py-1.5 text-center">
                                                <Checkbox
                                                    checked={content.data}
                                                    onCheckedChange={(
                                                        checked,
                                                    ) =>
                                                        setContent(table.name, {
                                                            ...content,
                                                            data:
                                                                checked ===
                                                                true,
                                                        })
                                                    }
                                                    aria-label={`Données de ${table.name}`}
                                                />
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                </div>

                <DialogFooter className="items-center">
                    <p className="mr-auto text-sm text-muted-foreground">
                        {chosen.length} table{chosen.length > 1 ? 's' : ''} sur{' '}
                        {tables.length}
                    </p>
                    <Button
                        variant="outline"
                        onClick={onClose}
                        disabled={processing}
                    >
                        Annuler
                    </Button>
                    <Button
                        onClick={download}
                        disabled={processing || chosen.length === 0}
                        icon={DownloadIcon}
                        processing={processing}
                    >
                        Exporter
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
