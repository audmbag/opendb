import {
    ArrowDownIcon,
    ArrowUpIcon,
    ClockIcon,
    KeyRoundIcon,
    PlusIcon,
    Trash2Icon,
    Undo2Icon,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import Alert from '@/components/alert';
import Button from '@/components/form/button';
import { Badge } from '@/components/reui/badge';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { FieldError } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    NativeSelect,
    NativeSelectOptGroup,
    NativeSelectOption,
} from '@/components/ui/native-select';
import { Skeleton } from '@/components/ui/skeleton';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { uppercaseSqlKeywords } from '@/lib/sql';
import { cn } from '@/lib/utils';
import TableBrowserController from '@/wayfinder/actions/App/Http/Controllers/Tables/TableBrowserController';
import TableDesignController from '@/wayfinder/actions/App/Http/Controllers/Tables/TableDesignController';
import type { TableRequestError } from '../api';
import { requestTableData } from '../api';
import SqlCodeBlock from '../sql-code-block';
import type {
    DefaultKind,
    DesignColumnType,
    ForeignKeyAction,
    TableDesign,
    TableOutline,
} from '../types';
import ColumnPicker from './column-picker';
import type {
    DesignerColumn,
    DesignerForeignKey,
    DesignerIndex,
} from './design';
import {
    definitionChanged,
    fromDesign,
    hasLength,
    hasPrecision,
    identifierColumn,
    INTEGER_TYPES,
    localId,
    locateErrors,
    newColumn,
    NUMERIC_TYPES,
    TEMPORAL_TYPES,
    toDesign,
    TYPE_GROUPS,
} from './design';

type Props = {
    projectId: string;
    /**
     * The engine of the database: mysql, mariadb, pgsql or sqlite.
     */
    driver: string;
    tables: string[];
    /**
     * The table to change, or null to create one.
     */
    table: string | null;
    onDone: (name: string) => void;
    onClose: () => void;
};

type Tab = 'columns' | 'indexes' | 'foreignKeys' | 'sql';

const ACTIONS: { value: ForeignKeyAction | ''; label: string }[] = [
    { value: '', label: 'Aucune action' },
    { value: 'cascade', label: 'CASCADE' },
    { value: 'restrict', label: 'RESTRICT' },
    { value: 'set null', label: 'SET NULL' },
    { value: 'set default', label: 'SET DEFAULT' },
];

const COLUMN_GRID =
    'grid grid-cols-[minmax(9rem,1fr)_9rem_8rem_3rem_minmax(11rem,14rem)_2.5rem_2.5rem_4.5rem] items-center gap-2';

/**
 * The designer of a table: its columns, its primary key, its indexes and its foreign keys. The statements it runs are
 * written by Laravel's schema builder for the engine, shown before they run, and refused at once by the server when
 * the design is not valid.
 */
export default function TableDesigner({
    projectId,
    driver,
    tables,
    table,
    onDone,
    onClose,
}: Props) {
    const creating = table === null;
    const mysql = driver === 'mysql' || driver === 'mariadb';

    const [loaded, setLoaded] = useState(creating);
    const [loadError, setLoadError] = useState<string | null>(null);
    const [name, setName] = useState(table ?? '');
    const [columns, setColumns] = useState<DesignerColumn[]>(() =>
        creating ? [identifierColumn()] : [],
    );
    const [primaryKey, setPrimaryKey] = useState<string[]>(() =>
        creating ? ['id'] : [],
    );
    const [indexes, setIndexes] = useState<DesignerIndex[]>([]);
    const [foreignKeys, setForeignKeys] = useState<DesignerForeignKey[]>([]);
    const [tab, setTab] = useState<Tab>('columns');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [statements, setStatements] = useState<string[] | null>(null);
    const [failure, setFailure] = useState<{
        message: string;
        statement: string | null;
    } | null>(null);
    const [processing, setProcessing] = useState<'preview' | 'apply' | null>(
        null,
    );
    const [targets, setTargets] = useState<Record<string, string[]>>({});

    useEffect(() => {
        if (creating) {
            return;
        }

        const controller = new AbortController();

        requestTableData<TableDesign>(
            'get',
            TableDesignController.show.url({
                project: projectId,
                table: encodeURIComponent(table),
            }),
            { signal: controller.signal },
        )
            .then((design) => {
                const state = fromDesign(design);

                setColumns(state.columns);
                setIndexes(state.indexes);
                setForeignKeys(state.foreignKeys);
                setPrimaryKey(design.primaryKey);
                setLoaded(true);
            })
            .catch(
                (exception: TableRequestError) =>
                    !exception.cancelled && setLoadError(exception.first()),
            );

        return () => controller.abort();
    }, [creating, projectId, table]);

    const keptColumns = columns.filter((column) => !column.removed);
    const columnNames = keptColumns
        .map((column) => column.name)
        .filter(Boolean);
    const error = (id: string, field = '') => errors[`${id}:${field}`];

    /**
     * The columns of a referenced table, read once for the choice of the referenced columns.
     */
    const loadTarget = (target: string) => {
        if (!target || targets[target]) {
            return;
        }

        if (target === name) {
            return;
        }

        requestTableData<TableOutline>(
            'get',
            TableBrowserController.outline.url({
                project: projectId,
                table: encodeURIComponent(target),
            }),
        )
            .then((outline) =>
                setTargets((current) => ({
                    ...current,
                    [target]: outline.columns.map((column) => column.name),
                })),
            )
            .catch(() => null);
    };

    const invalidate = () => {
        setStatements(null);
        setFailure(null);
    };

    const updateColumn = (id: string, changes: Partial<DesignerColumn>) => {
        invalidate();
        setColumns((current) =>
            current.map((column) => {
                if (column.id !== id) {
                    return column;
                }

                const next = { ...column, ...changes };

                if (changes.type && changes.type !== column.type) {
                    next.length = hasLength(changes.type)
                        ? (column.length ?? 255)
                        : null;
                    next.precision = hasPrecision(changes.type)
                        ? (column.precision ??
                          (changes.type === 'decimal' ? 10 : null))
                        : null;
                    next.scale =
                        changes.type === 'decimal' ? (column.scale ?? 2) : null;
                    next.autoIncrement =
                        column.autoIncrement &&
                        INTEGER_TYPES.includes(changes.type);

                    if (
                        next.defaultKind === 'current' &&
                        !TEMPORAL_TYPES.includes(changes.type)
                    ) {
                        next.defaultKind = 'none';
                    }
                }

                return next;
            }),
        );
    };

    /**
     * A renamed column keeps its place in the primary key, the indexes and the foreign keys.
     */
    const renameColumn = (column: DesignerColumn, next: string) => {
        const previous = column.name;
        const rename = (names: string[]) =>
            names.map((item) => (item === previous ? next : item));

        updateColumn(column.id, { name: next });
        setPrimaryKey(rename);
        setIndexes((current) =>
            current.map((index) => ({
                ...index,
                columns: rename(index.columns),
            })),
        );
        setForeignKeys((current) =>
            current.map((foreignKey) => ({
                ...foreignKey,
                columns: rename(foreignKey.columns),
            })),
        );
    };

    const togglePrimary = (column: DesignerColumn, checked: boolean) => {
        invalidate();

        const ordered = keptColumns
            .map((item) => item.name)
            .filter(
                (item) =>
                    (item === column.name
                        ? checked
                        : primaryKey.includes(item)) && item !== '',
            );

        setPrimaryKey(ordered);

        if (checked) {
            updateColumn(column.id, { nullable: false });
        } else if (column.autoIncrement) {
            updateColumn(column.id, { autoIncrement: false });
        }
    };

    const toggleAutoIncrement = (column: DesignerColumn, checked: boolean) => {
        invalidate();
        setColumns((current) =>
            current.map((item) =>
                item.id === column.id
                    ? {
                          ...item,
                          autoIncrement: checked,
                          nullable: checked ? false : item.nullable,
                      }
                    : {
                          ...item,
                          autoIncrement: checked ? false : item.autoIncrement,
                      },
            ),
        );

        if (checked) {
            setPrimaryKey([column.name]);
        }
    };

    const removeColumn = (column: DesignerColumn) => {
        invalidate();

        if (column.original === null) {
            setColumns((current) =>
                current.filter((item) => item.id !== column.id),
            );
        } else {
            updateColumn(column.id, { removed: true });
        }

        setPrimaryKey((current) =>
            current.filter((item) => item !== column.name),
        );
    };

    const moveColumn = (index: number, offset: number) => {
        invalidate();
        setColumns((current) => {
            const next = [...current];
            const [moved] = next.splice(index, 1);
            next.splice(index + offset, 0, moved);

            return next;
        });
    };

    const addTimestamps = () => {
        invalidate();
        setColumns((current) => [
            ...current,
            ...['created_at', 'updated_at']
                .filter(
                    (item) => !current.some((column) => column.name === item),
                )
                .map((item) =>
                    newColumn({
                        name: item,
                        type: 'timestamp',
                        length: null,
                        nullable: true,
                    }),
                ),
        ]);
    };

    const send = async (mode: 'preview' | 'apply') => {
        const { design, columnIds, indexIds, foreignKeyIds } = toDesign(
            name,
            columns,
            primaryKey,
            indexes,
            foreignKeys,
        );
        const args = { project: projectId };
        const url =
            table === null
                ? mode === 'preview'
                    ? TableDesignController.previewCreate.url(args)
                    : TableDesignController.store.url(args)
                : mode === 'preview'
                  ? TableDesignController.previewUpdate.url({
                        ...args,
                        table: encodeURIComponent(table),
                    })
                  : TableDesignController.update.url({
                        ...args,
                        table: encodeURIComponent(table),
                    });

        setProcessing(mode);
        setErrors({});
        setFailure(null);

        try {
            const result = await requestTableData<{ statements: string[] }>(
                mode === 'apply' && table !== null ? 'put' : 'post',
                url,
                { data: design },
            );

            if (mode === 'preview') {
                setStatements(result.statements);

                return;
            }

            toast.success(
                creating
                    ? `Table ${name} créée`
                    : `Structure de ${name} modifiée`,
                {
                    description: `${result.statements.length} instruction${result.statements.length > 1 ? 's' : ''} exécutée${result.statements.length > 1 ? 's' : ''}`,
                },
            );
            onDone(name);
        } catch (exception) {
            const failed = exception as TableRequestError;

            if (failed.errors.design) {
                setFailure({
                    message: failed.first('design'),
                    statement: failed.statement,
                });
                setTab('sql');

                return;
            }

            const located = locateErrors(failed.errors, {
                columns: columnIds,
                indexes: indexIds,
                foreignKeys: foreignKeyIds,
            });

            setErrors(located);
            setStatements(null);

            const firstKey = Object.keys(failed.errors)[0] ?? '';
            setTab(
                firstKey.startsWith('indexes')
                    ? 'indexes'
                    : firstKey.startsWith('foreignKeys')
                      ? 'foreignKeys'
                      : 'columns',
            );
            toast.error("La structure n'est pas valide", {
                description: failed.first(),
            });
        } finally {
            setProcessing(null);
        }
    };

    const changedTab = (next: Tab) => {
        setTab(next);

        if (next === 'sql' && statements === null && !failure) {
            void send('preview');
        }
    };

    const pendingCount = creating
        ? 0
        : columns.filter(
              (column) =>
                  column.removed ||
                  column.original === null ||
                  column.original !== column.name ||
                  definitionChanged(column),
          ).length;

    const general =
        errors.name ??
        errors['columns:'] ??
        errors.columns ??
        errors.primaryKey;

    return (
        <Dialog
            open
            onOpenChange={(open) => !open && processing === null && onClose()}
        >
            <DialogContent className="flex h-[90vh] flex-col gap-4 sm:max-w-6xl">
                <DialogHeader>
                    <DialogTitle>
                        {creating ? 'Nouvelle table' : `Structure de ${table}`}
                    </DialogTitle>
                    <DialogDescription>
                        {creating
                            ? 'Les instructions sont écrites pour votre moteur et affichées avant la création.'
                            : "Les colonnes que vous ne touchez pas restent telles quelles. Vérifiez le SQL avant de l'appliquer : MySQL et MariaDB ne peuvent pas annuler un changement de structure."}
                    </DialogDescription>
                </DialogHeader>

                {loadError ? (
                    <Alert
                        variant="destructive"
                        title="La structure n'a pas pu être lue"
                        message={loadError}
                    />
                ) : !loaded ? (
                    <div className="flex flex-col gap-2">
                        {Array.from({ length: 6 }, (_, index) => (
                            <Skeleton key={index} className="h-8" />
                        ))}
                    </div>
                ) : (
                    <>
                        <div className="flex flex-wrap items-end gap-4">
                            <div className="flex min-w-60 flex-col gap-2">
                                <Label htmlFor="designer-name">
                                    Nom de la table
                                </Label>
                                <Input
                                    id="designer-name"
                                    value={name}
                                    onChange={(event) => {
                                        invalidate();
                                        setName(event.target.value);
                                    }}
                                    aria-invalid={Boolean(errors.name)}
                                    autoFocus={creating}
                                    placeholder="produits"
                                />
                            </div>
                            <Tabs
                                value={tab}
                                onValueChange={(value) =>
                                    changedTab(value as Tab)
                                }
                            >
                                <TabsList>
                                    <TabsTrigger value="columns">
                                        Colonnes
                                        <span className="text-xs text-muted-foreground tabular-nums">
                                            {keptColumns.length}
                                        </span>
                                    </TabsTrigger>
                                    <TabsTrigger value="indexes">
                                        Index
                                        <span className="text-xs text-muted-foreground tabular-nums">
                                            {indexes.length}
                                        </span>
                                    </TabsTrigger>
                                    <TabsTrigger value="foreignKeys">
                                        Clés étrangères
                                        <span className="text-xs text-muted-foreground tabular-nums">
                                            {foreignKeys.length}
                                        </span>
                                    </TabsTrigger>
                                    <TabsTrigger value="sql">SQL</TabsTrigger>
                                </TabsList>
                            </Tabs>
                        </div>

                        {general && (
                            <Alert
                                variant="destructive"
                                title="La structure n'est pas valide"
                                message={general}
                            />
                        )}

                        <div className="min-h-0 flex-1 overflow-auto rounded-md border">
                            {tab === 'columns' && (
                                <div className="min-w-[60rem] p-3">
                                    <div
                                        className={cn(
                                            COLUMN_GRID,
                                            'px-1 pb-2 text-xs font-medium text-muted-foreground',
                                        )}
                                    >
                                        <span>Nom</span>
                                        <span>Type</span>
                                        <span>Taille</span>
                                        <span className="text-center">
                                            NULL
                                        </span>
                                        <span>Valeur par défaut</span>
                                        <span
                                            className="text-center"
                                            title="Clé primaire"
                                        >
                                            Clé
                                        </span>
                                        <span
                                            className="text-center"
                                            title="Auto-incrément"
                                        >
                                            AI
                                        </span>
                                        <span />
                                    </div>
                                    <ul className="flex flex-col gap-1.5">
                                        {columns.map((column, index) => (
                                            <ColumnRow
                                                key={column.id}
                                                column={column}
                                                index={index}
                                                last={
                                                    index === columns.length - 1
                                                }
                                                creating={creating}
                                                driver={driver}
                                                mysql={mysql}
                                                primary={primaryKey.includes(
                                                    column.name,
                                                )}
                                                error={(field) =>
                                                    error(column.id, field)
                                                }
                                                onChange={(changes) =>
                                                    updateColumn(
                                                        column.id,
                                                        changes,
                                                    )
                                                }
                                                onRename={(next) =>
                                                    renameColumn(column, next)
                                                }
                                                onPrimary={(checked) =>
                                                    togglePrimary(
                                                        column,
                                                        checked,
                                                    )
                                                }
                                                onAutoIncrement={(checked) =>
                                                    toggleAutoIncrement(
                                                        column,
                                                        checked,
                                                    )
                                                }
                                                onRemove={() =>
                                                    removeColumn(column)
                                                }
                                                onRestore={() =>
                                                    updateColumn(column.id, {
                                                        removed: false,
                                                    })
                                                }
                                                onMove={(offset) =>
                                                    moveColumn(index, offset)
                                                }
                                            />
                                        ))}
                                    </ul>
                                    <div className="flex gap-2 pt-3">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() => {
                                                invalidate();
                                                setColumns((current) => [
                                                    ...current,
                                                    newColumn(),
                                                ]);
                                            }}
                                        >
                                            <PlusIcon />
                                            Ajouter une colonne
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={addTimestamps}
                                        >
                                            <ClockIcon />
                                            Dates de création et de modification
                                        </Button>
                                    </div>
                                </div>
                            )}

                            {tab === 'indexes' && (
                                <div className="flex flex-col gap-2 p-3">
                                    {indexes.length === 0 && (
                                        <p className="text-sm text-muted-foreground">
                                            Un index accélère les recherches et
                                            les tris sur ses colonnes ; un index
                                            unique refuse deux lignes de même
                                            valeur.
                                        </p>
                                    )}
                                    {indexes.map((index, position) => (
                                        <div
                                            key={index.id}
                                            className="grid grid-cols-[minmax(10rem,1fr)_minmax(12rem,1.5fr)_7rem_2rem] items-center gap-2"
                                        >
                                            <Input
                                                value={index.name ?? ''}
                                                placeholder="Nom automatique"
                                                disabled={
                                                    index.original !== null
                                                }
                                                onChange={(event) => {
                                                    invalidate();
                                                    setIndexes((current) =>
                                                        current.map((item) =>
                                                            item.id === index.id
                                                                ? {
                                                                      ...item,
                                                                      name:
                                                                          event
                                                                              .target
                                                                              .value ||
                                                                          null,
                                                                  }
                                                                : item,
                                                        ),
                                                    );
                                                }}
                                                aria-label={`Nom de l'index ${position + 1}`}
                                                className="h-8"
                                            />
                                            <div>
                                                <ColumnPicker
                                                    columns={columnNames}
                                                    value={index.columns}
                                                    disabled={
                                                        index.original !== null
                                                    }
                                                    invalid={Boolean(
                                                        error(
                                                            index.id,
                                                            'columns',
                                                        ),
                                                    )}
                                                    onChange={(value) => {
                                                        invalidate();
                                                        setIndexes((current) =>
                                                            current.map(
                                                                (item) =>
                                                                    item.id ===
                                                                    index.id
                                                                        ? {
                                                                              ...item,
                                                                              columns:
                                                                                  value,
                                                                          }
                                                                        : item,
                                                            ),
                                                        );
                                                    }}
                                                    aria-label={`Colonnes de l'index ${position + 1}`}
                                                />
                                                {error(index.id, 'columns') && (
                                                    <FieldError className="mt-1 text-xs">
                                                        {error(
                                                            index.id,
                                                            'columns',
                                                        )}
                                                    </FieldError>
                                                )}
                                            </div>
                                            <Label className="flex items-center gap-2 font-normal">
                                                <Checkbox
                                                    checked={index.unique}
                                                    disabled={
                                                        index.original !== null
                                                    }
                                                    onCheckedChange={(
                                                        checked,
                                                    ) => {
                                                        invalidate();
                                                        setIndexes((current) =>
                                                            current.map(
                                                                (item) =>
                                                                    item.id ===
                                                                    index.id
                                                                        ? {
                                                                              ...item,
                                                                              unique:
                                                                                  checked ===
                                                                                  true,
                                                                          }
                                                                        : item,
                                                            ),
                                                        );
                                                    }}
                                                />
                                                Unique
                                            </Label>
                                            <Button
                                                variant="ghost"
                                                size="icon-sm"
                                                aria-label="Supprimer l'index"
                                                onClick={() => {
                                                    invalidate();
                                                    setIndexes((current) =>
                                                        current.filter(
                                                            (item) =>
                                                                item.id !==
                                                                index.id,
                                                        ),
                                                    );
                                                }}
                                            >
                                                <Trash2Icon />
                                            </Button>
                                        </div>
                                    ))}
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        className="self-start"
                                        onClick={() => {
                                            invalidate();
                                            setIndexes((current) => [
                                                ...current,
                                                {
                                                    id: localId(),
                                                    original: null,
                                                    name: null,
                                                    columns: [],
                                                    unique: false,
                                                },
                                            ]);
                                        }}
                                    >
                                        <PlusIcon />
                                        Ajouter un index
                                    </Button>
                                </div>
                            )}

                            {tab === 'foreignKeys' && (
                                <div className="flex min-w-[52rem] flex-col gap-2 p-3">
                                    {foreignKeys.length === 0 && (
                                        <p className="text-sm text-muted-foreground">
                                            Une clé étrangère garantit que la
                                            valeur de ses colonnes existe dans
                                            une autre table, et dit quoi faire
                                            quand cette ligne change.
                                        </p>
                                    )}
                                    {foreignKeys.length > 0 && (
                                        <div className="grid grid-cols-[1fr_auto_1fr_1fr_9rem_9rem_2rem] gap-2 px-1 text-xs font-medium text-muted-foreground">
                                            <span>Colonnes</span>
                                            <span />
                                            <span>Table référencée</span>
                                            <span>Colonnes référencées</span>
                                            <span>À la suppression</span>
                                            <span>À la modification</span>
                                            <span />
                                        </div>
                                    )}
                                    {foreignKeys.map((foreignKey, position) => {
                                        const existing =
                                            foreignKey.original !== null;
                                        const targetColumns =
                                            foreignKey.referencesTable === name
                                                ? columnNames
                                                : (targets[
                                                      foreignKey.referencesTable
                                                  ] ?? []);
                                        const update = (
                                            changes: Partial<DesignerForeignKey>,
                                        ) => {
                                            invalidate();
                                            setForeignKeys((current) =>
                                                current.map((item) =>
                                                    item.id === foreignKey.id
                                                        ? {
                                                              ...item,
                                                              ...changes,
                                                          }
                                                        : item,
                                                ),
                                            );
                                        };

                                        return (
                                            <div
                                                key={foreignKey.id}
                                                className="flex flex-col gap-1"
                                            >
                                                <div className="grid grid-cols-[1fr_auto_1fr_1fr_9rem_9rem_2rem] items-center gap-2">
                                                    <ColumnPicker
                                                        columns={columnNames}
                                                        value={
                                                            foreignKey.columns
                                                        }
                                                        disabled={existing}
                                                        onChange={(value) =>
                                                            update({
                                                                columns: value,
                                                            })
                                                        }
                                                        aria-label={`Colonnes de la clé ${position + 1}`}
                                                    />
                                                    <span className="text-muted-foreground">
                                                        →
                                                    </span>
                                                    <NativeSelect
                                                        size="sm"
                                                        className="w-full"
                                                        value={
                                                            foreignKey.referencesTable
                                                        }
                                                        disabled={existing}
                                                        onFocus={() =>
                                                            loadTarget(
                                                                foreignKey.referencesTable,
                                                            )
                                                        }
                                                        onChange={(event) => {
                                                            loadTarget(
                                                                event.target
                                                                    .value,
                                                            );
                                                            update({
                                                                referencesTable:
                                                                    event.target
                                                                        .value,
                                                                referencesColumns:
                                                                    [],
                                                            });
                                                        }}
                                                        aria-label={`Table référencée par la clé ${position + 1}`}
                                                    >
                                                        <NativeSelectOption
                                                            value=""
                                                            disabled
                                                        >
                                                            Choisir
                                                        </NativeSelectOption>
                                                        {[
                                                            ...new Set([
                                                                ...tables,
                                                                ...(existing
                                                                    ? [
                                                                          foreignKey.referencesTable,
                                                                      ]
                                                                    : []),
                                                            ]),
                                                        ].map((item) => (
                                                            <NativeSelectOption
                                                                key={item}
                                                                value={item}
                                                            >
                                                                {item}
                                                            </NativeSelectOption>
                                                        ))}
                                                    </NativeSelect>
                                                    <ColumnPicker
                                                        columns={
                                                            existing
                                                                ? foreignKey.referencesColumns
                                                                : targetColumns
                                                        }
                                                        value={
                                                            foreignKey.referencesColumns
                                                        }
                                                        disabled={
                                                            existing ||
                                                            !foreignKey.referencesTable
                                                        }
                                                        invalid={Boolean(
                                                            error(
                                                                foreignKey.id,
                                                                'referencesColumns',
                                                            ),
                                                        )}
                                                        onChange={(value) =>
                                                            update({
                                                                referencesColumns:
                                                                    value,
                                                            })
                                                        }
                                                        aria-label={`Colonnes référencées par la clé ${position + 1}`}
                                                    />
                                                    {(
                                                        [
                                                            'onDelete',
                                                            'onUpdate',
                                                        ] as const
                                                    ).map((field) => (
                                                        <NativeSelect
                                                            key={field}
                                                            size="sm"
                                                            className="w-full"
                                                            value={
                                                                foreignKey[
                                                                    field
                                                                ] ?? ''
                                                            }
                                                            disabled={existing}
                                                            onChange={(event) =>
                                                                update({
                                                                    [field]:
                                                                        (event
                                                                            .target
                                                                            .value ||
                                                                            null) as ForeignKeyAction | null,
                                                                })
                                                            }
                                                            aria-label={
                                                                field ===
                                                                'onDelete'
                                                                    ? 'À la suppression'
                                                                    : 'À la modification'
                                                            }
                                                        >
                                                            {ACTIONS.map(
                                                                (action) => (
                                                                    <NativeSelectOption
                                                                        key={
                                                                            action.value
                                                                        }
                                                                        value={
                                                                            action.value
                                                                        }
                                                                    >
                                                                        {
                                                                            action.label
                                                                        }
                                                                    </NativeSelectOption>
                                                                ),
                                                            )}
                                                        </NativeSelect>
                                                    ))}
                                                    <Button
                                                        variant="ghost"
                                                        size="icon-sm"
                                                        aria-label="Supprimer la clé étrangère"
                                                        onClick={() => {
                                                            invalidate();
                                                            setForeignKeys(
                                                                (current) =>
                                                                    current.filter(
                                                                        (
                                                                            item,
                                                                        ) =>
                                                                            item.id !==
                                                                            foreignKey.id,
                                                                    ),
                                                            );
                                                        }}
                                                    >
                                                        <Trash2Icon />
                                                    </Button>
                                                </div>
                                                {(error(
                                                    foreignKey.id,
                                                    'referencesColumns',
                                                ) ||
                                                    error(
                                                        foreignKey.id,
                                                        'columns',
                                                    ) ||
                                                    error(
                                                        foreignKey.id,
                                                        'referencesTable',
                                                    )) && (
                                                    <FieldError className="text-xs">
                                                        {error(
                                                            foreignKey.id,
                                                            'referencesColumns',
                                                        ) ??
                                                            error(
                                                                foreignKey.id,
                                                                'columns',
                                                            ) ??
                                                            error(
                                                                foreignKey.id,
                                                                'referencesTable',
                                                            )}
                                                    </FieldError>
                                                )}
                                            </div>
                                        );
                                    })}
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        className="self-start"
                                        onClick={() => {
                                            invalidate();
                                            setForeignKeys((current) => [
                                                ...current,
                                                {
                                                    id: localId(),
                                                    original: null,
                                                    name: null,
                                                    columns: [],
                                                    referencesTable: '',
                                                    referencesColumns: [],
                                                    onDelete: null,
                                                    onUpdate: null,
                                                },
                                            ]);
                                        }}
                                    >
                                        <PlusIcon />
                                        Ajouter une clé étrangère
                                    </Button>
                                </div>
                            )}

                            {tab === 'sql' && (
                                <div className="flex flex-col gap-3 p-3">
                                    {failure && (
                                        <Alert
                                            variant="destructive"
                                            title="La base a refusé le changement"
                                            message={failure.message}
                                        >
                                            {failure.statement && (
                                                <code className="font-mono text-xs break-all opacity-80">
                                                    {uppercaseSqlKeywords(
                                                        failure.statement,
                                                    )}
                                                </code>
                                            )}
                                        </Alert>
                                    )}
                                    {processing === 'preview' && (
                                        <Skeleton className="h-32" />
                                    )}
                                    {statements && statements.length === 0 && (
                                        <p className="text-sm text-muted-foreground">
                                            Rien à changer : la structure est
                                            déjà celle-ci.
                                        </p>
                                    )}
                                    {statements && statements.length > 0 && (
                                        <SqlCodeBlock
                                            code={statements
                                                .map(
                                                    (statement) =>
                                                        `${statement};`,
                                                )
                                                .join('\n\n')}
                                            className="[&_pre]:break-all [&_pre]:whitespace-pre-wrap"
                                        />
                                    )}
                                </div>
                            )}
                        </div>
                    </>
                )}

                <DialogFooter className="items-center">
                    {!creating && pendingCount > 0 && (
                        <p className="mr-auto text-sm text-muted-foreground">
                            {pendingCount} colonne{pendingCount > 1 ? 's' : ''}{' '}
                            modifiée
                            {pendingCount > 1 ? 's' : ''}
                        </p>
                    )}
                    <Button
                        variant="outline"
                        onClick={onClose}
                        disabled={processing !== null}
                    >
                        Annuler
                    </Button>
                    {tab === 'sql' && statements !== null ? (
                        <Button
                            onClick={() => send('apply')}
                            disabled={
                                processing !== null || statements.length === 0
                            }
                            processing={processing === 'apply'}
                        >
                            {creating
                                ? 'Créer la table'
                                : 'Appliquer les changements'}
                        </Button>
                    ) : (
                        <Button
                            onClick={() => changedTab('sql')}
                            disabled={
                                processing !== null ||
                                !loaded ||
                                name.trim() === ''
                            }
                            processing={processing === 'preview'}
                        >
                            Vérifier le SQL
                        </Button>
                    )}
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function ColumnRow({
    column,
    index,
    last,
    creating,
    driver,
    mysql,
    primary,
    error,
    onChange,
    onRename,
    onPrimary,
    onAutoIncrement,
    onRemove,
    onRestore,
    onMove,
}: {
    column: DesignerColumn;
    index: number;
    last: boolean;
    creating: boolean;
    driver: string;
    mysql: boolean;
    primary: boolean;
    error: (field: string) => string | undefined;
    onChange: (changes: Partial<DesignerColumn>) => void;
    onRename: (name: string) => void;
    onPrimary: (checked: boolean) => void;
    onAutoIncrement: (checked: boolean) => void;
    onRemove: () => void;
    onRestore: () => void;
    onMove: (offset: number) => void;
}) {
    const label = column.name || `colonne ${index + 1}`;
    // The values are kept as typed, so that a comma can be typed before the next value.
    const [optionsText, setOptionsText] = useState(() =>
        column.options.join(', '),
    );
    const changed =
        column.original !== null &&
        (column.original !== column.name || definitionChanged(column));
    const messages = [
        'name',
        'type',
        'length',
        'precision',
        'scale',
        'default',
        'options',
        'autoIncrement',
    ]
        .map(error)
        .filter(Boolean);

    if (column.removed) {
        return (
            <li
                className={cn(
                    COLUMN_GRID,
                    'rounded-md bg-destructive/5 px-1 py-1 text-sm',
                )}
            >
                <span className="truncate text-muted-foreground line-through decoration-destructive/60">
                    {column.original}
                </span>
                <span className="col-span-6 text-xs text-destructive">
                    Supprimée avec ses données
                </span>
                <Button variant="ghost" size="sm" onClick={onRestore}>
                    <Undo2Icon />
                    Garder
                </Button>
            </li>
        );
    }

    return (
        <li
            className={cn(
                'flex flex-col gap-1 rounded-md px-1 py-1',
                column.original === null && !creating && 'bg-success/5',
                changed && 'bg-warning/10 dark:bg-warning/15',
            )}
        >
            <div className={COLUMN_GRID}>
                <div className="flex min-w-0 items-center gap-1.5">
                    {primary && (
                        <KeyRoundIcon
                            className="size-3.5 shrink-0 text-warning"
                            aria-label="Clé primaire"
                        />
                    )}
                    <Input
                        value={column.name}
                        onChange={(event) => onRename(event.target.value)}
                        aria-label={`Nom de ${label}`}
                        aria-invalid={Boolean(error('name'))}
                        placeholder="nom"
                        className="h-8 font-mono text-sm"
                    />
                </div>
                <NativeSelect
                    size="sm"
                    className="w-full"
                    value={column.type}
                    onChange={(event) =>
                        onChange({
                            type: event.target.value as DesignColumnType,
                        })
                    }
                    aria-label={`Type de ${label}`}
                    aria-invalid={Boolean(error('type'))}
                >
                    {column.type === 'custom' && (
                        <NativeSelectOption value="custom" disabled>
                            {column.dbType ?? 'autre'}
                        </NativeSelectOption>
                    )}
                    {TYPE_GROUPS.map((group) => (
                        <NativeSelectOptGroup
                            key={group.label}
                            label={group.label}
                        >
                            {group.types
                                .filter(
                                    (type) =>
                                        !type.engines ||
                                        type.engines.includes(driver),
                                )
                                .map((type) => (
                                    <NativeSelectOption
                                        key={type.value}
                                        value={type.value}
                                    >
                                        {type.label}
                                    </NativeSelectOption>
                                ))}
                        </NativeSelectOptGroup>
                    ))}
                </NativeSelect>
                <div className="flex items-center gap-1">
                    {hasLength(column.type) && (
                        <Input
                            type="number"
                            min={1}
                            value={column.length ?? ''}
                            onChange={(event) =>
                                onChange({
                                    length:
                                        event.target.value === ''
                                            ? null
                                            : Number(event.target.value),
                                })
                            }
                            aria-label={`Longueur de ${label}`}
                            className="h-8 tabular-nums"
                        />
                    )}
                    {hasPrecision(column.type) && (
                        <Input
                            type="number"
                            min={1}
                            value={column.precision ?? ''}
                            onChange={(event) =>
                                onChange({
                                    precision:
                                        event.target.value === ''
                                            ? null
                                            : Number(event.target.value),
                                })
                            }
                            aria-label={`Précision de ${label}`}
                            title="Nombre total de chiffres"
                            className="h-8 tabular-nums"
                        />
                    )}
                    {column.type === 'decimal' && (
                        <Input
                            type="number"
                            min={0}
                            value={column.scale ?? ''}
                            onChange={(event) =>
                                onChange({
                                    scale:
                                        event.target.value === ''
                                            ? null
                                            : Number(event.target.value),
                                })
                            }
                            aria-label={`Décimales de ${label}`}
                            title="Chiffres après la virgule"
                            className="h-8 tabular-nums"
                        />
                    )}
                    {mysql && NUMERIC_TYPES.includes(column.type) && (
                        <Label
                            className="flex items-center gap-1 text-xs font-normal whitespace-nowrap"
                            title="Sans signe : valeurs positives seulement"
                        >
                            <Checkbox
                                checked={column.unsigned}
                                onCheckedChange={(checked) =>
                                    onChange({ unsigned: checked === true })
                                }
                            />
                            +
                        </Label>
                    )}
                </div>
                <div className="flex justify-center">
                    <Checkbox
                        checked={column.nullable}
                        disabled={primary}
                        onCheckedChange={(checked) =>
                            onChange({ nullable: checked === true })
                        }
                        aria-label={`${label} peut être NULL`}
                    />
                </div>
                <div className="flex min-w-0 items-center gap-1">
                    <NativeSelect
                        size="sm"
                        className="w-28 shrink-0"
                        value={column.defaultKind}
                        disabled={column.autoIncrement}
                        onChange={(event) =>
                            onChange({
                                defaultKind: event.target.value as DefaultKind,
                            })
                        }
                        aria-label={`Valeur par défaut de ${label}`}
                    >
                        <NativeSelectOption value="none">
                            Aucune
                        </NativeSelectOption>
                        <NativeSelectOption value="value">
                            Valeur
                        </NativeSelectOption>
                        {TEMPORAL_TYPES.includes(column.type) && (
                            <NativeSelectOption value="current">
                                Maintenant
                            </NativeSelectOption>
                        )}
                        <NativeSelectOption value="expression">
                            Expression
                        </NativeSelectOption>
                    </NativeSelect>
                    {(column.defaultKind === 'value' ||
                        column.defaultKind === 'expression') && (
                        <Input
                            value={column.default ?? ''}
                            onChange={(event) =>
                                onChange({ default: event.target.value })
                            }
                            placeholder={
                                column.defaultKind === 'expression'
                                    ? 'SQL'
                                    : 'valeur'
                            }
                            aria-label={`Défaut de ${label}`}
                            aria-invalid={Boolean(error('default'))}
                            className={cn(
                                'h-8 min-w-0',
                                column.defaultKind === 'expression' &&
                                    'font-mono text-xs',
                            )}
                        />
                    )}
                </div>
                <div className="flex justify-center">
                    <Checkbox
                        checked={primary}
                        onCheckedChange={(checked) =>
                            onPrimary(checked === true)
                        }
                        aria-label={`${label} dans la clé primaire`}
                    />
                </div>
                <div className="flex justify-center">
                    <Checkbox
                        checked={column.autoIncrement}
                        disabled={!INTEGER_TYPES.includes(column.type)}
                        onCheckedChange={(checked) =>
                            onAutoIncrement(checked === true)
                        }
                        aria-label={`${label} auto-incrémentée`}
                    />
                </div>
                <div className="flex items-center justify-end">
                    {creating && (
                        <>
                            <Button
                                variant="ghost"
                                size="icon-xs"
                                disabled={index === 0}
                                onClick={() => onMove(-1)}
                                aria-label={`Monter ${label}`}
                            >
                                <ArrowUpIcon />
                            </Button>
                            <Button
                                variant="ghost"
                                size="icon-xs"
                                disabled={last}
                                onClick={() => onMove(1)}
                                aria-label={`Descendre ${label}`}
                            >
                                <ArrowDownIcon />
                            </Button>
                        </>
                    )}
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        onClick={onRemove}
                        aria-label={`Supprimer ${label}`}
                    >
                        <Trash2Icon />
                    </Button>
                </div>
            </div>

            {column.type === 'enum' && (
                <div className="flex items-center gap-2 pl-6">
                    <span className="text-xs text-muted-foreground">
                        Valeurs
                    </span>
                    <Input
                        value={optionsText}
                        onChange={(event) => {
                            setOptionsText(event.target.value);
                            onChange({
                                options: event.target.value
                                    .split(',')
                                    .map((option) => option.trim())
                                    .filter(Boolean),
                            });
                        }}
                        placeholder="brouillon, publié, archivé"
                        aria-label={`Valeurs de ${label}`}
                        aria-invalid={Boolean(error('options'))}
                        className="h-8 max-w-xl"
                    />
                </div>
            )}

            {column.original !== null && column.original !== column.name && (
                <p className="pl-6 text-xs text-muted-foreground">
                    Renommée depuis{' '}
                    <Badge variant="outline">{column.original}</Badge>
                </p>
            )}

            {messages.length > 0 && (
                <p className="pl-6 text-xs text-destructive">{messages[0]}</p>
            )}
        </li>
    );
}
