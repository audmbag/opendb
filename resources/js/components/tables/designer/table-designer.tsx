import { useLaravelReactI18n } from 'laravel-react-i18n';
import {
    AlignLeftIcon,
    ArrowDownIcon,
    ArrowUpIcon,
    ClockIcon,
    KeyRoundIcon,
    PencilRulerIcon,
    PlusIcon,
    Trash2Icon,
    Undo2Icon,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import Alert from '@/components/alert';
import { CodeBlockActions, CodeBlockHeader } from '@/components/code-block';
import Button from '@/components/form/button';
import Modal from '@/components/modals/modal';
import ModalFooter from '@/components/modals/modal-footer';
import { Badge } from '@/components/reui/badge';
import { Checkbox } from '@/components/ui/checkbox';
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
import { formatSql, uppercaseSqlKeywords } from '@/lib/sql';
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

export const ACTIONS: { value: ForeignKeyAction | ''; label: string }[] = [
    { value: '', label: 'tables.designer.no_action' },
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
    const { t, tChoice } = useLaravelReactI18n();
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
    // Kept with the statements it lays out: new statements show as they come until formatted again.
    const [formatted, setFormatted] = useState<{
        statements: string[];
        sql: string;
    } | null>(null);
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
                    ? t('tables.designer.created', { table: name })
                    : t('tables.designer.updated', { table: name }),
                {
                    description: tChoice(
                        'tables.designer.statements_run',
                        result.statements.length,
                    ),
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
            toast.error(t('tables.designer.invalid'), {
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

    const script =
        statements?.map((statement) => `${statement};`).join('\n\n') ?? '';
    const isFormatted =
        formatted !== null && formatted.statements === statements;

    /**
     * Lays the statements out one clause per line, as the console formats its script.
     */
    const formatScript = () => {
        if (!statements) {
            return;
        }

        try {
            setFormatted({ statements, sql: formatSql(script, driver) });
        } catch (exception) {
            toast.error(t('tables.console.format_failed'), {
                description: (exception as Error).message.split('\n')[0],
            });
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
        <Modal
            open
            showHeader
            className="h-[90vh] sm:max-w-6xl"
            onOpenChange={(open) => !open && processing === null && onClose()}
            icon={PencilRulerIcon}
            title={
                creating
                    ? t('tables.designer.new_table')
                    : t('tables.designer.structure_of', { table: table ?? '' })
            }
            description={
                creating
                    ? t('tables.designer.create_description')
                    : t('tables.designer.update_description')
            }
        >
            <div className="flex h-full flex-col">
                <div className="flex min-h-0 flex-1 flex-col gap-4 p-4">
                    {loadError ? (
                        <Alert
                            variant="destructive"
                            title={t('tables.designer.load_failed')}
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
                                        {t('tables.designer.name')}
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
                                        placeholder={t(
                                            'tables.designer.name_placeholder',
                                        )}
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
                                            {t('tables.designer.tabs.columns')}
                                            <span className="text-xs text-muted-foreground tabular-nums">
                                                {keptColumns.length}
                                            </span>
                                        </TabsTrigger>
                                        <TabsTrigger value="indexes">
                                            {t('tables.designer.tabs.indexes')}
                                            <span className="text-xs text-muted-foreground tabular-nums">
                                                {indexes.length}
                                            </span>
                                        </TabsTrigger>
                                        <TabsTrigger value="foreignKeys">
                                            {t(
                                                'tables.designer.tabs.foreign_keys',
                                            )}
                                            <span className="text-xs text-muted-foreground tabular-nums">
                                                {foreignKeys.length}
                                            </span>
                                        </TabsTrigger>
                                        <TabsTrigger value="sql">
                                            SQL
                                        </TabsTrigger>
                                    </TabsList>
                                </Tabs>
                            </div>

                            {general && (
                                <Alert
                                    variant="destructive"
                                    title={t('tables.designer.invalid')}
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
                                            <span>
                                                {t(
                                                    'tables.designer.columns.name',
                                                )}
                                            </span>
                                            <span>
                                                {t(
                                                    'tables.designer.columns.type',
                                                )}
                                            </span>
                                            <span>
                                                {t(
                                                    'tables.designer.columns.size',
                                                )}
                                            </span>
                                            <span className="text-center">
                                                NULL
                                            </span>
                                            <span>
                                                {t(
                                                    'tables.designer.columns.default',
                                                )}
                                            </span>
                                            <span
                                                className="text-center"
                                                title={t(
                                                    'tables.designer.columns.primary_key',
                                                )}
                                            >
                                                {t(
                                                    'tables.designer.columns.key',
                                                )}
                                            </span>
                                            <span
                                                className="text-center"
                                                title={t(
                                                    'tables.designer.columns.auto_increment',
                                                )}
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
                                                        index ===
                                                        columns.length - 1
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
                                                        renameColumn(
                                                            column,
                                                            next,
                                                        )
                                                    }
                                                    onPrimary={(checked) =>
                                                        togglePrimary(
                                                            column,
                                                            checked,
                                                        )
                                                    }
                                                    onAutoIncrement={(
                                                        checked,
                                                    ) =>
                                                        toggleAutoIncrement(
                                                            column,
                                                            checked,
                                                        )
                                                    }
                                                    onRemove={() =>
                                                        removeColumn(column)
                                                    }
                                                    onRestore={() =>
                                                        updateColumn(
                                                            column.id,
                                                            {
                                                                removed: false,
                                                            },
                                                        )
                                                    }
                                                    onMove={(offset) =>
                                                        moveColumn(
                                                            index,
                                                            offset,
                                                        )
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
                                                {t(
                                                    'tables.designer.add_column',
                                                )}
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={addTimestamps}
                                            >
                                                <ClockIcon />
                                                {t(
                                                    'tables.designer.add_timestamps',
                                                )}
                                            </Button>
                                        </div>
                                    </div>
                                )}

                                {tab === 'indexes' && (
                                    <div className="flex flex-col gap-2 p-3">
                                        {indexes.length === 0 && (
                                            <p className="text-sm text-muted-foreground">
                                                {t(
                                                    'tables.designer.indexes_help',
                                                )}
                                            </p>
                                        )}
                                        {indexes.map((index, position) => (
                                            <div
                                                key={index.id}
                                                className="grid grid-cols-[minmax(10rem,1fr)_minmax(12rem,1.5fr)_7rem_2rem] items-center gap-2"
                                            >
                                                <Input
                                                    value={index.name ?? ''}
                                                    placeholder={t(
                                                        'tables.designer.automatic_name',
                                                    )}
                                                    disabled={
                                                        index.original !== null
                                                    }
                                                    onChange={(event) => {
                                                        invalidate();
                                                        setIndexes((current) =>
                                                            current.map(
                                                                (item) =>
                                                                    item.id ===
                                                                    index.id
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
                                                    aria-label={t(
                                                        'tables.designer.index_name',
                                                        {
                                                            number:
                                                                position + 1,
                                                        },
                                                    )}
                                                    className="h-8"
                                                />
                                                <div>
                                                    <ColumnPicker
                                                        columns={columnNames}
                                                        value={index.columns}
                                                        disabled={
                                                            index.original !==
                                                            null
                                                        }
                                                        invalid={Boolean(
                                                            error(
                                                                index.id,
                                                                'columns',
                                                            ),
                                                        )}
                                                        onChange={(value) => {
                                                            invalidate();
                                                            setIndexes(
                                                                (current) =>
                                                                    current.map(
                                                                        (
                                                                            item,
                                                                        ) =>
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
                                                        aria-label={t(
                                                            'tables.designer.index_columns',
                                                            {
                                                                number:
                                                                    position +
                                                                    1,
                                                            },
                                                        )}
                                                    />
                                                    {error(
                                                        index.id,
                                                        'columns',
                                                    ) && (
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
                                                            index.original !==
                                                            null
                                                        }
                                                        onCheckedChange={(
                                                            checked,
                                                        ) => {
                                                            invalidate();
                                                            setIndexes(
                                                                (current) =>
                                                                    current.map(
                                                                        (
                                                                            item,
                                                                        ) =>
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
                                                    {t(
                                                        'tables.designer.unique',
                                                    )}
                                                </Label>
                                                <Button
                                                    variant="ghost"
                                                    size="icon-sm"
                                                    aria-label={t(
                                                        'tables.designer.remove_index',
                                                    )}
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
                                            {t('tables.designer.add_index')}
                                        </Button>
                                    </div>
                                )}

                                {tab === 'foreignKeys' && (
                                    <div className="flex min-w-[52rem] flex-col gap-2 p-3">
                                        {foreignKeys.length === 0 && (
                                            <p className="text-sm text-muted-foreground">
                                                {t(
                                                    'tables.designer.foreign_keys_help',
                                                )}
                                            </p>
                                        )}
                                        {foreignKeys.length > 0 && (
                                            <div className="grid grid-cols-[1fr_auto_1fr_1fr_9rem_9rem_2rem] gap-2 px-1 text-xs font-medium text-muted-foreground">
                                                <span>
                                                    {t(
                                                        'tables.designer.tabs.columns',
                                                    )}
                                                </span>
                                                <span />
                                                <span>
                                                    {t(
                                                        'tables.designer.referenced_table',
                                                    )}
                                                </span>
                                                <span>
                                                    {t(
                                                        'tables.designer.referenced_columns',
                                                    )}
                                                </span>
                                                <span>
                                                    {t(
                                                        'tables.designer.on_delete',
                                                    )}
                                                </span>
                                                <span>
                                                    {t(
                                                        'tables.designer.on_update',
                                                    )}
                                                </span>
                                                <span />
                                            </div>
                                        )}
                                        {foreignKeys.map(
                                            (foreignKey, position) => {
                                                const existing =
                                                    foreignKey.original !==
                                                    null;
                                                const targetColumns =
                                                    foreignKey.referencesTable ===
                                                    name
                                                        ? columnNames
                                                        : (targets[
                                                              foreignKey
                                                                  .referencesTable
                                                          ] ?? []);
                                                const update = (
                                                    changes: Partial<DesignerForeignKey>,
                                                ) => {
                                                    invalidate();
                                                    setForeignKeys((current) =>
                                                        current.map((item) =>
                                                            item.id ===
                                                            foreignKey.id
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
                                                                columns={
                                                                    columnNames
                                                                }
                                                                value={
                                                                    foreignKey.columns
                                                                }
                                                                disabled={
                                                                    existing
                                                                }
                                                                onChange={(
                                                                    value,
                                                                ) =>
                                                                    update({
                                                                        columns:
                                                                            value,
                                                                    })
                                                                }
                                                                aria-label={t(
                                                                    'tables.designer.key_columns',
                                                                    {
                                                                        number:
                                                                            position +
                                                                            1,
                                                                    },
                                                                )}
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
                                                                disabled={
                                                                    existing
                                                                }
                                                                onFocus={() =>
                                                                    loadTarget(
                                                                        foreignKey.referencesTable,
                                                                    )
                                                                }
                                                                onChange={(
                                                                    event,
                                                                ) => {
                                                                    loadTarget(
                                                                        event
                                                                            .target
                                                                            .value,
                                                                    );
                                                                    update({
                                                                        referencesTable:
                                                                            event
                                                                                .target
                                                                                .value,
                                                                        referencesColumns:
                                                                            [],
                                                                    });
                                                                }}
                                                                aria-label={t(
                                                                    'tables.designer.key_table',
                                                                    {
                                                                        number:
                                                                            position +
                                                                            1,
                                                                    },
                                                                )}
                                                            >
                                                                <NativeSelectOption
                                                                    value=""
                                                                    disabled
                                                                >
                                                                    {t(
                                                                        'tables.designer.choose',
                                                                    )}
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
                                                                ].map(
                                                                    (item) => (
                                                                        <NativeSelectOption
                                                                            key={
                                                                                item
                                                                            }
                                                                            value={
                                                                                item
                                                                            }
                                                                        >
                                                                            {
                                                                                item
                                                                            }
                                                                        </NativeSelectOption>
                                                                    ),
                                                                )}
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
                                                                onChange={(
                                                                    value,
                                                                ) =>
                                                                    update({
                                                                        referencesColumns:
                                                                            value,
                                                                    })
                                                                }
                                                                aria-label={t(
                                                                    'tables.designer.key_referenced_columns',
                                                                    {
                                                                        number:
                                                                            position +
                                                                            1,
                                                                    },
                                                                )}
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
                                                                    disabled={
                                                                        existing
                                                                    }
                                                                    onChange={(
                                                                        event,
                                                                    ) =>
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
                                                                            ? t(
                                                                                  'tables.designer.on_delete',
                                                                              )
                                                                            : t(
                                                                                  'tables.designer.on_update',
                                                                              )
                                                                    }
                                                                >
                                                                    {ACTIONS.map(
                                                                        (
                                                                            action,
                                                                        ) => (
                                                                            <NativeSelectOption
                                                                                key={
                                                                                    action.value
                                                                                }
                                                                                value={
                                                                                    action.value
                                                                                }
                                                                            >
                                                                                {t(
                                                                                    action.label,
                                                                                )}
                                                                            </NativeSelectOption>
                                                                        ),
                                                                    )}
                                                                </NativeSelect>
                                                            ))}
                                                            <Button
                                                                variant="ghost"
                                                                size="icon-sm"
                                                                aria-label={t(
                                                                    'tables.designer.remove_foreign_key',
                                                                )}
                                                                onClick={() => {
                                                                    invalidate();
                                                                    setForeignKeys(
                                                                        (
                                                                            current,
                                                                        ) =>
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
                                            },
                                        )}
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
                                            {t(
                                                'tables.designer.add_foreign_key',
                                            )}
                                        </Button>
                                    </div>
                                )}

                                {tab === 'sql' && (
                                    <div className="flex flex-col gap-3 p-3">
                                        {failure && (
                                            <Alert
                                                variant="destructive"
                                                title={t(
                                                    'tables.designer.refused',
                                                )}
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
                                        {statements &&
                                            statements.length === 0 && (
                                                <p className="text-sm text-muted-foreground">
                                                    {t(
                                                        'tables.designer.nothing_to_change',
                                                    )}
                                                </p>
                                            )}
                                        {statements &&
                                            statements.length > 0 && (
                                                <SqlCodeBlock
                                                    code={
                                                        isFormatted
                                                            ? formatted.sql
                                                            : script
                                                    }
                                                    className="[&_pre]:break-all [&_pre]:whitespace-pre-wrap"
                                                >
                                                    <CodeBlockHeader className="justify-end py-1.5">
                                                        <CodeBlockActions>
                                                            <Button
                                                                variant="ghost"
                                                                size="xs"
                                                                icon={
                                                                    AlignLeftIcon
                                                                }
                                                                onClick={
                                                                    formatScript
                                                                }
                                                                disabled={
                                                                    isFormatted
                                                                }
                                                                tooltip={t(
                                                                    'tables.console.format_tooltip',
                                                                )}
                                                            >
                                                                {t(
                                                                    'tables.console.format',
                                                                )}
                                                            </Button>
                                                        </CodeBlockActions>
                                                    </CodeBlockHeader>
                                                </SqlCodeBlock>
                                            )}
                                    </div>
                                )}
                            </div>
                        </>
                    )}
                </div>

                <ModalFooter>
                    {!creating && pendingCount > 0 && (
                        <p className="mr-auto text-sm text-muted-foreground">
                            {tChoice(
                                'tables.designer.changed_columns',
                                pendingCount,
                            )}
                        </p>
                    )}
                    <Button
                        variant="outline"
                        onClick={onClose}
                        disabled={processing !== null}
                    >
                        {t('common.cancel')}
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
                                ? t('tables.designer.create')
                                : t('tables.designer.apply')}
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
                            {t('tables.designer.check_sql')}
                        </Button>
                    )}
                </ModalFooter>
            </div>
        </Modal>
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
    const { t } = useLaravelReactI18n();
    const label =
        column.name ||
        t('tables.designer.column_number', { number: index + 1 });
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
                    {t('tables.designer.removed_with_data')}
                </span>
                <Button variant="ghost" size="sm" onClick={onRestore}>
                    <Undo2Icon />
                    {t('tables.designer.keep')}
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
                            aria-label={t(
                                'tables.designer.columns.primary_key',
                            )}
                        />
                    )}
                    <Input
                        value={column.name}
                        onChange={(event) => onRename(event.target.value)}
                        aria-label={t('tables.designer.aria.name', {
                            column: label,
                        })}
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
                    aria-label={t('tables.designer.aria.type', {
                        column: label,
                    })}
                    aria-invalid={Boolean(error('type'))}
                >
                    {column.type === 'custom' && (
                        <NativeSelectOption value="custom" disabled>
                            {column.dbType ?? t('tables.designer.other_type')}
                        </NativeSelectOption>
                    )}
                    {TYPE_GROUPS.map((group) => (
                        <NativeSelectOptGroup
                            key={group.label}
                            label={t(group.label)}
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
                            aria-label={t('tables.designer.aria.length', {
                                column: label,
                            })}
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
                            aria-label={t('tables.designer.aria.precision', {
                                column: label,
                            })}
                            title={t('tables.designer.precision_title')}
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
                            aria-label={t('tables.designer.aria.scale', {
                                column: label,
                            })}
                            title={t('tables.designer.scale_title')}
                            className="h-8 tabular-nums"
                        />
                    )}
                    {mysql && NUMERIC_TYPES.includes(column.type) && (
                        <Label
                            className="flex items-center gap-1 text-xs font-normal whitespace-nowrap"
                            title={t('tables.designer.unsigned_title')}
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
                        aria-label={t('tables.designer.aria.nullable', {
                            column: label,
                        })}
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
                        aria-label={t('tables.designer.aria.default_kind', {
                            column: label,
                        })}
                    >
                        <NativeSelectOption value="none">
                            {t('tables.designer.defaults.none')}
                        </NativeSelectOption>
                        <NativeSelectOption value="value">
                            {t('tables.designer.defaults.value')}
                        </NativeSelectOption>
                        {TEMPORAL_TYPES.includes(column.type) && (
                            <NativeSelectOption value="current">
                                {t('tables.designer.defaults.now')}
                            </NativeSelectOption>
                        )}
                        <NativeSelectOption value="expression">
                            {t('tables.designer.defaults.expression')}
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
                            aria-label={t('tables.designer.aria.default', {
                                column: label,
                            })}
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
                        aria-label={t('tables.designer.aria.primary', {
                            column: label,
                        })}
                    />
                </div>
                <div className="flex justify-center">
                    <Checkbox
                        checked={column.autoIncrement}
                        disabled={!INTEGER_TYPES.includes(column.type)}
                        onCheckedChange={(checked) =>
                            onAutoIncrement(checked === true)
                        }
                        aria-label={t('tables.designer.aria.auto_increment', {
                            column: label,
                        })}
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
                        aria-label={t('tables.designer.aria.remove', {
                            column: label,
                        })}
                    >
                        <Trash2Icon />
                    </Button>
                </div>
            </div>

            {column.type === 'enum' && (
                <div className="flex items-center gap-2 pl-6">
                    <span className="text-xs text-muted-foreground">
                        {t('tables.designer.values')}
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
                        placeholder={t('tables.designer.values_placeholder')}
                        aria-label={t('tables.designer.aria.values', {
                            column: label,
                        })}
                        aria-invalid={Boolean(error('options'))}
                        className="h-8 max-w-xl"
                    />
                </div>
            )}

            {column.original !== null && column.original !== column.name && (
                <p className="pl-6 text-xs text-muted-foreground">
                    {t('tables.designer.renamed_from')}{' '}
                    <Badge variant="outline">{column.original}</Badge>
                </p>
            )}

            {messages.length > 0 && (
                <p className="pl-6 text-xs text-destructive">{messages[0]}</p>
            )}
        </li>
    );
}
