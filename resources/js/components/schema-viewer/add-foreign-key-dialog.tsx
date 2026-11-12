import { useLaravelReactI18n } from 'laravel-react-i18n';
import { ArrowRightIcon, LinkIcon } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';
import Alert from '@/components/alert';
import Button from '@/components/form/button';
import Modal from '@/components/modals/modal';
import ModalFooter from '@/components/modals/modal-footer';
import type { TableRequestError } from '@/components/tables/api';
import { requestTableData } from '@/components/tables/api';
import {
    fromDesign,
    localId,
    toDesign,
} from '@/components/tables/designer/design';
import { ACTIONS } from '@/components/tables/designer/table-designer';
import SqlCodeBlock from '@/components/tables/sql-code-block';
import type { ForeignKeyAction, TableDesign } from '@/components/tables/types';
import { Label } from '@/components/ui/label';
import {
    NativeSelect,
    NativeSelectOption,
} from '@/components/ui/native-select';
import { Skeleton } from '@/components/ui/skeleton';
import { uppercaseSqlKeywords } from '@/lib/sql';
import TableDesignController from '@/wayfinder/actions/App/Http/Controllers/Tables/TableDesignController';
import type { ColumnEnd, SchemaExport } from './types';

type Props = {
    projectId: string;
    /**
     * The column that will hold the foreign key.
     */
    source: ColumnEnd;
    /**
     * The column it references.
     */
    target: ColumnEnd;
    /**
     * The structure drawn, to tell whether the referenced column identifies a row.
     */
    schema: SchemaExport;
    onClose: () => void;
    onDone: () => void;
};

type Actions = {
    onDelete: ForeignKeyAction | null;
    onUpdate: ForeignKeyAction | null;
};

/**
 * A foreign key added to a table from the diagram. The structure of the table is read, the key added to it, and the
 * statements of the designer shown before they run, as when the table is changed in the designer.
 */
export default function AddForeignKeyDialog({
    projectId,
    source,
    target,
    schema,
    onClose,
    onDone,
}: Props) {
    const { t } = useLaravelReactI18n();
    const [design, setDesign] = useState<TableDesign | null>(null);
    const [loadError, setLoadError] = useState<string | null>(null);
    const [actions, setActions] = useState<Actions>({
        onDelete: null,
        onUpdate: null,
    });
    const [statements, setStatements] = useState<string[] | null>(null);
    const [failure, setFailure] = useState<{
        message: string;
        statement: string | null;
    } | null>(null);
    const [isApplying, setIsApplying] = useState(false);
    const tableUrl = {
        project: projectId,
        table: encodeURIComponent(source.table),
    };

    /**
     * Whether the referenced column alone is the primary key or a unique index: otherwise the database may refuse the
     * constraint.
     */
    const identifiesRow = useMemo(() => {
        const referenced = schema.find((table) => table.name === target.table);
        const isOnly = (columns: string[]) =>
            columns.length === 1 && columns[0] === target.column;

        return (
            referenced !== undefined &&
            (isOnly(referenced.primary_key) ||
                referenced.indexes.some(
                    (index) => index.unique && isOnly(index.columns),
                ))
        );
    }, [schema, target]);

    useEffect(() => {
        const controller = new AbortController();

        requestTableData<TableDesign>(
            'get',
            TableDesignController.show.url({
                project: projectId,
                table: encodeURIComponent(source.table),
            }),
            { signal: controller.signal },
        )
            .then(setDesign)
            .catch(
                (exception: TableRequestError) =>
                    !exception.cancelled && setLoadError(exception.first()),
            );

        return () => controller.abort();
    }, [projectId, source.table]);

    /**
     * The design of the table read from the database, with the new foreign key after its own.
     */
    const changedDesign = useMemo(() => {
        if (!design) {
            return null;
        }

        const state = fromDesign(design);

        return toDesign(
            design.name,
            state.columns,
            design.primaryKey,
            state.indexes,
            [
                ...state.foreignKeys,
                {
                    id: localId(),
                    original: null,
                    name: null,
                    columns: [source.column],
                    referencesTable: target.table,
                    referencesColumns: [target.column],
                    ...actions,
                },
            ],
        ).design;
    }, [actions, design, source.column, target]);

    useEffect(() => {
        if (!changedDesign) {
            return;
        }

        const controller = new AbortController();

        requestTableData<{ statements: string[] }>(
            'post',
            TableDesignController.previewUpdate.url({
                project: projectId,
                table: encodeURIComponent(source.table),
            }),
            { data: changedDesign, signal: controller.signal },
        )
            .then((result) => setStatements(result.statements))
            .catch(
                (exception: TableRequestError) =>
                    !exception.cancelled &&
                    setFailure({
                        message: exception.first(),
                        statement: exception.statement,
                    }),
            );

        return () => controller.abort();
    }, [changedDesign, projectId, source.table]);

    const apply = async () => {
        if (!changedDesign) {
            return;
        }

        setIsApplying(true);
        setFailure(null);

        try {
            await requestTableData(
                'put',
                TableDesignController.update.url(tableUrl),
                { data: changedDesign },
            );

            toast.success(
                t('tables.designer.updated', { table: source.table }),
            );
            onDone();
        } catch (exception) {
            const failed = exception as TableRequestError;

            setFailure({
                message: failed.first(),
                statement: failed.statement,
            });
        } finally {
            setIsApplying(false);
        }
    };

    return (
        <Modal
            open
            showHeader
            scrollable
            className="h-auto sm:max-w-2xl"
            onOpenChange={(open) => !open && !isApplying && onClose()}
            icon={LinkIcon}
            title={t('erd.relation.title')}
            description={t('erd.relation.description')}
        >
            <div className="flex flex-col">
                <div className="flex flex-col gap-4 p-4">
                    <p className="flex flex-wrap items-center gap-2 font-mono text-sm">
                        <span className="rounded-md bg-muted px-2 py-1">
                            {source.table}.{source.column}
                        </span>
                        <ArrowRightIcon
                            className="size-4 text-muted-foreground"
                            aria-label={t('tables.structure.references')}
                        />
                        <span className="rounded-md bg-muted px-2 py-1">
                            {target.table}.{target.column}
                        </span>
                    </p>

                    {!identifiesRow && (
                        <Alert
                            variant="warning"
                            title={t('erd.relation.not_unique_title')}
                            message={t('erd.relation.not_unique', {
                                table: target.table,
                                column: target.column,
                            })}
                        />
                    )}

                    <div className="grid gap-3 sm:grid-cols-2">
                        {(['onDelete', 'onUpdate'] as const).map((field) => (
                            <div key={field} className="flex flex-col gap-2">
                                <Label htmlFor={`relation-${field}`}>
                                    {field === 'onDelete'
                                        ? t('tables.designer.on_delete')
                                        : t('tables.designer.on_update')}
                                </Label>
                                <NativeSelect
                                    id={`relation-${field}`}
                                    className="w-full"
                                    value={actions[field] ?? ''}
                                    disabled={isApplying}
                                    onChange={(event) => {
                                        // The statements of the previous actions no longer apply.
                                        setStatements(null);
                                        setFailure(null);
                                        setActions((current) => ({
                                            ...current,
                                            [field]: (event.target.value ||
                                                null) as ForeignKeyAction | null,
                                        }));
                                    }}
                                >
                                    {ACTIONS.map((action) => (
                                        <NativeSelectOption
                                            key={action.value}
                                            value={action.value}
                                        >
                                            {t(action.label)}
                                        </NativeSelectOption>
                                    ))}
                                </NativeSelect>
                            </div>
                        ))}
                    </div>

                    <div className="flex flex-col gap-3">
                        {loadError && (
                            <Alert
                                variant="destructive"
                                title={t('tables.designer.load_failed')}
                                message={loadError}
                            />
                        )}
                        {failure && (
                            <Alert
                                variant="destructive"
                                title={t('tables.designer.refused')}
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
                        {!loadError && !failure && statements === null && (
                            <Skeleton className="h-24" />
                        )}
                        {statements && statements.length > 0 && (
                            <SqlCodeBlock
                                code={statements
                                    .map((statement) => `${statement};`)
                                    .join('\n\n')}
                                className="[&_pre]:break-all [&_pre]:whitespace-pre-wrap"
                            />
                        )}
                    </div>
                </div>

                <ModalFooter>
                    <Button
                        variant="outline"
                        onClick={onClose}
                        disabled={isApplying}
                    >
                        {t('common.cancel')}
                    </Button>
                    <Button
                        onClick={() => void apply()}
                        disabled={!statements?.length || failure !== null}
                        processing={isApplying}
                    >
                        {t('erd.relation.create')}
                    </Button>
                </ModalFooter>
            </div>
        </Modal>
    );
}
