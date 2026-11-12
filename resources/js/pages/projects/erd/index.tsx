import { Head, router } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import {
    Code2Icon,
    CrosshairIcon,
    DatabaseIcon,
    DownloadIcon,
    EyeOffIcon,
    FocusIcon,
    LayoutGridIcon,
    MessageSquareIcon,
    PencilRulerIcon,
    PlusIcon,
    WandSparklesIcon,
    SlidersHorizontalIcon,
    SparklesIcon,
    Table2Icon,
    XIcon,
} from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { useDefaultLayout } from 'react-resizable-panels';
import { toast } from 'sonner';
import { EmptyState } from '@/components/empty-state';
import Button from '@/components/form/button';
import AddForeignKeyDialog from '@/components/schema-viewer/add-foreign-key-dialog';
import { schemaWithDraft } from '@/components/schema-viewer/assistant/draft';
import DraftBar from '@/components/schema-viewer/assistant/draft-bar';
import ErdAssistantPanel from '@/components/schema-viewer/assistant/erd-assistant-panel';
import type {
    SchemaChangePreview,
    SchemaChangeResult,
} from '@/components/schema-viewer/assistant/types';
import { useSchemaAssistant } from '@/components/schema-viewer/assistant/use-schema-assistant';
import {
    findingsByTable,
    implicitRelationships,
} from '@/components/schema-viewer/diagnostics';
import DiagnosticsPopover from '@/components/schema-viewer/diagnostics-popover';
import SchemaCanvas from '@/components/schema-viewer/schema-canvas';
import TableInspector from '@/components/schema-viewer/table-inspector';
import { SchemaTableMenuContext } from '@/components/schema-viewer/table-menu';
import type { SchemaTableMenu } from '@/components/schema-viewer/table-menu';
import TableVisibilityFilter from '@/components/schema-viewer/table-visibility-filter';
import type {
    ColumnEnd,
    Point,
    SchemaColorBy,
    SchemaDetail,
    SchemaDiagnostics,
    SchemaExport,
} from '@/components/schema-viewer/types';
import type { SqlFile } from '@/components/sql-editor/types';
import type { TableRequestError } from '@/components/tables/api';
import { requestTableData } from '@/components/tables/api';
import TableDesigner from '@/components/tables/designer/table-designer';
import { downloadFile } from '@/components/tables/download';
import TableList from '@/components/tables/table-list';
import type { TableSummary } from '@/components/tables/types';
import { ButtonGroup } from '@/components/ui/button-group';
import {
    ContextMenuItem,
    ContextMenuSeparator,
} from '@/components/ui/context-menu';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    ResizableHandle,
    ResizablePanel,
    ResizablePanelGroup,
} from '@/components/ui/resizable';
import { useConversation } from '@/hooks/use-conversation';
import { usePrivileges } from '@/hooks/use-privileges';
import { latestRowsQuery } from '@/lib/sql';
import type { Project } from '@/types/models/project';
import ErdAssistantController from '@/wayfinder/actions/App/Http/Controllers/Erd/ErdAssistantController';
import ErdController from '@/wayfinder/actions/App/Http/Controllers/Erd/ErdController';
import SqlEditorController from '@/wayfinder/actions/App/Http/Controllers/SqlEditor/SqlEditorController';
import SqlFileController from '@/wayfinder/actions/App/Http/Controllers/SqlEditor/SqlFileController';
import TableBrowserController from '@/wayfinder/actions/App/Http/Controllers/Tables/TableBrowserController';

type Props = {
    project: Project;
    tables: TableSummary[];
    schema: SchemaExport;
    /**
     * The tables the user placed in the diagram.
     */
    layout: { table: string; x: number; y: number }[];
    /**
     * The findings of the doctor, deferred: undefined until it has answered.
     */
    diagnostics?: SchemaDiagnostics;
};

const COLOR_MODES: SchemaColorBy[] = ['table', 'prefix', 'component'];

/**
 * The time left after the last move of a table before the places are saved.
 */
const SAVE_DELAY = 500;

const DETAILS: { value: SchemaDetail; label: string; tooltip: string }[] = [
    {
        value: 'all',
        label: 'erd.detail.all',
        tooltip: 'erd.detail.all_tooltip',
    },
    {
        value: 'keys',
        label: 'erd.detail.keys',
        tooltip: 'erd.detail.keys_tooltip',
    },
    {
        value: 'names',
        label: 'erd.detail.names',
        tooltip: 'erd.detail.names_tooltip',
    },
];

const EXPORT_FORMATS = ['dbml', 'mermaid', 'markdown', 'json'] as const;

/**
 * The tables linked to the given one by a foreign key, in both directions, with the table itself.
 */
function neighbourhood(schema: SchemaExport, table: string): Set<string> {
    const names = new Set([table]);

    schema.forEach((other) =>
        other.foreign_keys.forEach((foreignKey) => {
            if (other.name === table) {
                names.add(foreignKey.references_table);
            } else if (foreignKey.references_table === table) {
                names.add(other.name);
            }
        }),
    );

    return names;
}

/**
 * The ERD tab: the diagram of the whole database, with the list of tables to find one, the tables to show, the level
 * of detail of each table, an inspector of the selected table, and its export as text.
 */
export default function ErdIndex({
    project,
    tables,
    schema,
    layout,
    diagnostics,
}: Props) {
    const { t } = useLaravelReactI18n();
    // The structure is only changed with the privileges that change it, which the assistant of the diagram needs too.
    const { canOnProject, canSomewhere } = usePrivileges();
    const canCreateTable = canOnProject('create');
    const canAlter = useCallback((table: string) => canOnProject('alter', table), [canOnProject]);
    const changesStructure = (['create', 'alter', 'drop', 'index'] as const).some(canSomewhere);
    const { startConversation } = useConversation();
    const [detail, setDetail] = useState<SchemaDetail>('all');
    const [hiddenTables, setHiddenTables] = useState<Set<string>>(
        () => new Set(),
    );
    const [isolatedTable, setIsolatedTable] = useState<string | null>(null);
    const [selectedTable, setSelectedTable] = useState<string | null>(null);
    const [centeredTable, setCenteredTable] = useState<{
        name: string;
        others?: string[];
        requestId: number;
    } | null>(null);
    const [designedTable, setDesignedTable] = useState<{
        table: string | null;
    } | null>(null);
    const [schemaVersion, setSchemaVersion] = useState(0);
    const [isExporting, setIsExporting] = useState(false);
    const [positions, setPositions] = useState<Record<string, Point>>(() =>
        Object.fromEntries(
            layout.map((position) => [
                position.table,
                { x: position.x, y: position.y },
            ]),
        ),
    );
    // A new diagram, laid out again, each time the user asks for it.
    const [layoutRequest, setLayoutRequest] = useState(0);
    const [colorBy, setColorBy] = useState<SchemaColorBy>('table');
    const [showCardinality, setShowCardinality] = useState(true);
    const [showMinimap, setShowMinimap] = useState(true);
    const [showFindings, setShowFindings] = useState(true);
    const [showImplicit, setShowImplicit] = useState(true);
    const [newRelation, setNewRelation] = useState<{
        source: ColumnEnd;
        target: ColumnEnd;
    } | null>(null);
    const saveTimer = useRef<ReturnType<typeof setTimeout>>(undefined);
    const [isAssistantOpen, setIsAssistantOpen] = useState(false);
    // The draft of the changes waiting for the user, read by the server for the tool call proposing them.
    const [draftPreview, setDraftPreview] = useState<{
        toolCallId: string;
        preview: SchemaChangePreview;
    } | null>(null);
    // The draft the user applied stays drawn until the diagram is read again with the changes.
    const [applyingPreview, setApplyingPreview] =
        useState<SchemaChangePreview | null>(null);

    useEffect(() => () => clearTimeout(saveTimer.current), []);

    const driver =
        project.driver_config?.driver_id ??
        project.driver_config?.driver?.id ??
        '';
    const tableNames = useMemo(() => tables.map((item) => item.name), [tables]);

    /**
     * Read the tables, the diagram and its diagnostic again, after a change of structure.
     */
    const reloadSchema = useCallback((onSuccess?: () => void) => {
        setSchemaVersion((version) => version + 1);
        router.reload({ only: ['tables', 'schema', 'diagnostics'], onSuccess });
    }, []);

    /**
     * Once the assistant ran changes, the diagram is read again and the first table changed brought into the view.
     */
    const finishAssistantAnswer = useCallback(
        (result: SchemaChangeResult | null) => {
            if (!result) {
                setApplyingPreview(null);

                return;
            }

            const changed = [
                ...new Set(
                    (result.applied ?? [])
                        .filter((change) => change.kind !== 'drop_table')
                        .map((change) => change.table)
                        .filter((table): table is string => !!table),
                ),
            ];

            if (result.success) {
                toast.success(t('erd.assistant.applied'));
            } else {
                toast.error(t('erd.assistant.apply_failed'), {
                    description: [
                        result.error,
                        result.applied?.length
                            ? t('erd.assistant.partially_applied', {
                                  tables: result.applied
                                      .map((change) => change.table)
                                      .filter(Boolean)
                                      .join(', '),
                              })
                            : null,
                    ]
                        .filter(Boolean)
                        .join(' '),
                });
            }

            reloadSchema(() => {
                setApplyingPreview(null);

                // One table changed is selected; several are framed together.
                const [first, ...others] = changed;

                if (first) {
                    if (!others.length) {
                        setSelectedTable(first);
                    }

                    setCenteredTable({
                        name: first,
                        others,
                        requestId: Date.now(),
                    });
                }
            });
        },
        [reloadSchema, t],
    );

    const assistant = useSchemaAssistant({
        projectId: project.id,
        onFinished: finishAssistantAnswer,
    });
    const { proposal } = assistant;

    /**
     * The changes proposed by the assistant, read against the database to be drawn as a draft.
     */
    useEffect(() => {
        if (!proposal) {
            return;
        }

        const controller = new AbortController();

        requestTableData<SchemaChangePreview>(
            'post',
            ErdAssistantController.preview.url({ project: project.id }),
            { data: { changes: proposal.changes }, signal: controller.signal },
        )
            .then((preview) => {
                setDraftPreview({ toolCallId: proposal.toolCallId, preview });

                // Every table of the draft is framed, the dropped ones too.
                const [first, ...others] = [
                    ...preview.draft.tables.map((table) => table.name),
                    ...preview.draft.dropped,
                ];

                if (first) {
                    setCenteredTable({
                        name: first,
                        others,
                        requestId: Date.now(),
                    });
                }
            })
            .catch(
                (exception: TableRequestError) =>
                    !exception.cancelled &&
                    setDraftPreview({
                        toolCallId: proposal.toolCallId,
                        preview: {
                            valid: false,
                            destructive: false,
                            changes: [],
                            errors: [exception.first()],
                            statements: [],
                            failure: null,
                            draft: { tables: [], dropped: [] },
                        },
                    }),
            );

        return () => controller.abort();
    }, [project.id, proposal]);

    const proposalPreview =
        proposal && draftPreview?.toolCallId === proposal.toolCallId
            ? draftPreview.preview
            : null;
    const draft = proposal
        ? (proposalPreview?.draft ?? null)
        : (applyingPreview?.draft ?? null);

    /**
     * The schema with the draft of the assistant drawn over it.
     */
    const drawnSchema = useMemo(
        () => schemaWithDraft(schema, draft),
        [draft, schema],
    );

    const answerProposal = (approved: boolean) => {
        setApplyingPreview(approved ? proposalPreview : null);
        assistant.respond(approved);
    };

    /**
     * The tables drawn: the isolated table with its neighbours, or every table the user did not hide. A new array
     * only when one of them changes, since each one lays the diagram out again.
     */
    const shownSchema = useMemo(() => {
        if (isolatedTable) {
            const names = neighbourhood(drawnSchema, isolatedTable);

            return drawnSchema.filter((table) => names.has(table.name));
        }

        return hiddenTables.size
            ? drawnSchema.filter((table) => !hiddenTables.has(table.name))
            : drawnSchema;
    }, [drawnSchema, hiddenTables, isolatedTable]);

    const findingCounts = useMemo(
        () => (showFindings ? findingsByTable(diagnostics) : undefined),
        [diagnostics, showFindings],
    );
    const implicitLinks = useMemo(
        () => (showImplicit ? implicitRelationships(diagnostics) : undefined),
        [diagnostics, showImplicit],
    );

    const inspectedTable = useMemo(
        () => shownSchema.find((table) => table.name === selectedTable) ?? null,
        [selectedTable, shownSchema],
    );

    /**
     * Select a table and bring it into the view, showing it first when it is hidden or out of the isolated tables.
     */
    const showTable = useCallback(
        (name: string) => {
            if (
                isolatedTable &&
                !neighbourhood(schema, isolatedTable).has(name)
            ) {
                setIsolatedTable(null);
            }

            setHiddenTables((current) => {
                if (!current.has(name)) {
                    return current;
                }

                const next = new Set(current);

                next.delete(name);

                return next;
            });
            setSelectedTable(name);
            setCenteredTable({ name, requestId: Date.now() });
        },
        [isolatedTable, schema],
    );

    const isolate = useCallback((name: string) => {
        setIsolatedTable(name);
        setSelectedTable(name);
        setCenteredTable(null);
    }, []);

    const exitIsolation = () => {
        setIsolatedTable(null);
        setCenteredTable(
            selectedTable
                ? { name: selectedTable, requestId: Date.now() }
                : null,
        );
    };

    const openData = useCallback(
        (name: string) =>
            router.visit(
                TableBrowserController.index.url({
                    project: project.id,
                    table: encodeURIComponent(name),
                }),
            ),
        [project.id],
    );

    /**
     * A new file of the SQL editor reading the latest rows of the table, opened in its tab.
     */
    const newQuery = useCallback(
        async (name: string) => {
            const columns =
                schema
                    .find((table) => table.name === name)
                    ?.columns.map((column) => column.name) ?? [];

            try {
                const file = await requestTableData<SqlFile>(
                    'post',
                    SqlFileController.store.url({ project: project.id }),
                    { data: { sql: latestRowsQuery(name, columns, driver) } },
                );

                router.visit(
                    SqlEditorController.index.url(
                        { project: project.id },
                        { query: { open: file.name } },
                    ),
                );
            } catch (exception) {
                toast.error(t('erd.actions.query_failed'), {
                    description: (exception as TableRequestError).first(),
                });
            }
        },
        [driver, project.id, schema, t],
    );

    const exportSchema = async (format: (typeof EXPORT_FORMATS)[number]) => {
        setIsExporting(true);

        try {
            const name = await downloadFile(
                ErdController.export.url({ project: project.id }),
                { format },
            );

            toast.success(t('erd.export.downloaded'), { description: name });
        } catch (exception) {
            toast.error(t('erd.export.failed'), {
                description: (exception as TableRequestError).first(),
            });
        } finally {
            setIsExporting(false);
        }
    };

    const saveLayout = useCallback(
        (next: Record<string, Point>) =>
            requestTableData(
                'put',
                ErdController.saveLayout.url({ project: project.id }),
                {
                    data: {
                        positions: Object.entries(next).map(
                            ([table, position]) => ({ table, ...position }),
                        ),
                    },
                },
            ).catch((exception: TableRequestError) =>
                toast.error(t('erd.layout.failed'), {
                    description: exception.first(),
                }),
            ),
        [project.id, t],
    );

    /**
     * The places of the tables drawn, merged with those of the hidden tables, saved once the user stops moving them.
     * The places of the tables dropped from the database are forgotten.
     */
    const movePositions = useCallback(
        (moved: Record<string, Point>) => {
            const existing = new Set(tableNames);
            const next = Object.fromEntries(
                Object.entries({ ...positions, ...moved }).filter(([table]) =>
                    existing.has(table),
                ),
            );

            setPositions(next);
            clearTimeout(saveTimer.current);
            saveTimer.current = setTimeout(
                () => void saveLayout(next),
                SAVE_DELAY,
            );
        },
        [positions, saveLayout, tableNames],
    );

    const resetLayout = () => {
        clearTimeout(saveTimer.current);
        setPositions({});
        setLayoutRequest((request) => request + 1);
        void saveLayout({});
    };

    const askChat = useCallback(
        (text: string) => startConversation({ text, files: [] }),
        [startConversation],
    );

    const explainSchema = () =>
        askChat(
            isolatedTable
                ? t('erd.ai.explain_isolated', { table: isolatedTable })
                : t('erd.ai.explain'),
        );

    const connectColumns = useCallback(
        (source: ColumnEnd, target: ColumnEnd) =>
            setNewRelation({ source, target }),
        [],
    );

    const tableMenu = useCallback<SchemaTableMenu>(
        (name) => (
            <>
                <ContextMenuItem onSelect={() => showTable(name)}>
                    <CrosshairIcon />
                    {t('erd.actions.show')}
                </ContextMenuItem>
                <ContextMenuItem onSelect={() => isolate(name)}>
                    <FocusIcon />
                    {t('erd.focus.show_neighbours')}
                </ContextMenuItem>
                <ContextMenuSeparator />
                <ContextMenuItem onSelect={() => openData(name)}>
                    <Table2Icon />
                    {t('erd.actions.open_data')}
                </ContextMenuItem>
                <ContextMenuItem onSelect={() => void newQuery(name)}>
                    <Code2Icon />
                    {t('erd.actions.new_query')}
                </ContextMenuItem>
                {canAlter(name) && (
                    <ContextMenuItem
                        onSelect={() => setDesignedTable({ table: name })}
                    >
                        <PencilRulerIcon />
                        {t('tables.workspace.edit_structure')}
                    </ContextMenuItem>
                )}
                <ContextMenuSeparator />
                <ContextMenuItem
                    onSelect={() =>
                        askChat(
                            t('tables.workspace.ask_table', { table: name }),
                        )
                    }
                >
                    <MessageSquareIcon />
                    {t('erd.ai.ask_table')}
                </ContextMenuItem>
            </>
        ),
        [askChat, canAlter, isolate, newQuery, openData, showTable, t],
    );

    const { defaultLayout, onLayoutChanged } = useDefaultLayout({
        id: 'project-erd-layout',
        storage: localStorage,
        panelIds: isAssistantOpen
            ? ['erd-tables', 'erd-diagram', 'erd-assistant']
            : ['erd-tables', 'erd-diagram'],
    });

    return (
        <>
            <Head title={t('projects.menu.erd')} />

            <ResizablePanelGroup
                orientation="horizontal"
                defaultLayout={defaultLayout}
                onLayoutChanged={onLayoutChanged}
                className="h-full min-h-0 w-full gap-1"
            >
                <ResizablePanel
                    id="erd-tables"
                    defaultSize="272px"
                    minSize="200px"
                    maxSize="460px"
                    className="flex flex-col overflow-hidden rounded-lg border bg-sidebar"
                >
                    <TableList
                        projectId={project.id}
                        tables={tables}
                        current={selectedTable}
                        openTables={[]}
                        schemaVersion={schemaVersion}
                        onOpen={showTable}
                        onCreateTable={canCreateTable ? () => setDesignedTable({ table: null }) : undefined}
                    />
                </ResizablePanel>
                <ResizableHandle className="bg-transparent" />
                <ResizablePanel
                    id="erd-diagram"
                    className="flex min-w-0 flex-col overflow-hidden rounded-lg border bg-background"
                >
                    <div
                        role="toolbar"
                        aria-label={t('erd.toolbar')}
                        className="flex flex-wrap items-center gap-2 border-b px-2 py-1.5"
                    >
                        <TableVisibilityFilter
                            tables={tableNames}
                            hiddenTables={hiddenTables}
                            onChange={setHiddenTables}
                            disabled={isolatedTable !== null || !tables.length}
                        />
                        {isolatedTable && (
                            <span className="inline-flex h-8 min-w-0 items-center gap-1 rounded-md bg-muted pr-1 pl-2.5 text-sm">
                                <FocusIcon className="size-3.5 shrink-0 text-muted-foreground" />
                                <span className="truncate">
                                    {t('erd.focus.active', {
                                        table: isolatedTable,
                                    })}
                                </span>
                                <Button
                                    variant="ghost"
                                    size="icon-xs"
                                    onClick={exitIsolation}
                                    aria-label={t('erd.focus.exit')}
                                    tooltip={t('erd.focus.exit')}
                                >
                                    <XIcon />
                                </Button>
                            </span>
                        )}
                        <div className="ml-auto flex flex-wrap items-center gap-2">
                            {changesStructure && (
                                <Button
                                    variant={
                                        isAssistantOpen ? 'secondary' : 'outline'
                                    }
                                    size="sm"
                                    aria-pressed={isAssistantOpen}
                                    tooltip={t('erd.assistant.open')}
                                    onClick={() =>
                                        setIsAssistantOpen((open) => !open)
                                    }
                                >
                                    <WandSparklesIcon />
                                    {t('erd.assistant.label')}
                                </Button>
                            )}
                            {canCreateTable && (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() =>
                                        setDesignedTable({ table: null })
                                    }
                                >
                                    <PlusIcon />
                                    {t('erd.add_table')}
                                </Button>
                            )}
                            <DiagnosticsPopover
                                diagnostics={diagnostics}
                                onShowTable={showTable}
                            />
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={!tables.length}
                                tooltip={t('erd.ai.explain_tooltip')}
                                onClick={explainSchema}
                            >
                                <SparklesIcon />
                                {t('erd.ai.explain_label')}
                            </Button>
                            <ButtonGroup aria-label={t('erd.detail.label')}>
                                {DETAILS.map((item) => (
                                    <Button
                                        key={item.value}
                                        variant={
                                            detail === item.value
                                                ? 'secondary'
                                                : 'outline'
                                        }
                                        size="sm"
                                        aria-pressed={detail === item.value}
                                        tooltip={t(item.tooltip)}
                                        onClick={() => setDetail(item.value)}
                                    >
                                        {t(item.label)}
                                    </Button>
                                ))}
                            </ButtonGroup>
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <Button variant="outline" size="sm">
                                        <SlidersHorizontalIcon />
                                        {t('erd.view.label')}
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent
                                    align="end"
                                    className="w-64"
                                >
                                    <DropdownMenuLabel>
                                        {t('erd.view.colors')}
                                    </DropdownMenuLabel>
                                    <DropdownMenuRadioGroup
                                        value={colorBy}
                                        onValueChange={(value) =>
                                            setColorBy(value as SchemaColorBy)
                                        }
                                    >
                                        {COLOR_MODES.map((mode) => (
                                            <DropdownMenuRadioItem
                                                key={mode}
                                                value={mode}
                                            >
                                                {t(`erd.view.color_by.${mode}`)}
                                            </DropdownMenuRadioItem>
                                        ))}
                                    </DropdownMenuRadioGroup>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuCheckboxItem
                                        checked={showCardinality}
                                        onCheckedChange={setShowCardinality}
                                    >
                                        {t('erd.view.cardinality')}
                                    </DropdownMenuCheckboxItem>
                                    <DropdownMenuCheckboxItem
                                        checked={showImplicit}
                                        onCheckedChange={setShowImplicit}
                                    >
                                        {t('erd.view.implicit')}
                                    </DropdownMenuCheckboxItem>
                                    <DropdownMenuCheckboxItem
                                        checked={showFindings}
                                        onCheckedChange={setShowFindings}
                                    >
                                        {t('erd.view.findings')}
                                    </DropdownMenuCheckboxItem>
                                    <DropdownMenuCheckboxItem
                                        checked={showMinimap}
                                        onCheckedChange={setShowMinimap}
                                    >
                                        {t('erd.view.minimap')}
                                    </DropdownMenuCheckboxItem>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem
                                        disabled={
                                            isolatedTable !== null ||
                                            !Object.keys(positions).length
                                        }
                                        onSelect={resetLayout}
                                    >
                                        <LayoutGridIcon />
                                        {t('erd.layout.reset')}
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        processing={isExporting}
                                        disabled={!tables.length}
                                    >
                                        <DownloadIcon />
                                        {t('erd.export.label')}
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end">
                                    {EXPORT_FORMATS.map((format) => (
                                        <DropdownMenuItem
                                            key={format}
                                            onSelect={() =>
                                                void exportSchema(format)
                                            }
                                        >
                                            {t(`erd.export.formats.${format}`)}
                                        </DropdownMenuItem>
                                    ))}
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>
                    </div>

                    <div className="relative min-h-0 flex-1">
                        {(proposal || applyingPreview) && (
                            <div className="pointer-events-none absolute inset-x-0 top-2 z-10 flex justify-center px-2">
                                <DraftBar
                                    preview={
                                        proposal
                                            ? proposalPreview
                                            : applyingPreview
                                    }
                                    driver={driver}
                                    isResponding={!proposal}
                                    onApply={() => answerProposal(true)}
                                    onReject={() => answerProposal(false)}
                                />
                            </div>
                        )}
                        {!drawnSchema.length ? (
                            <EmptyState
                                icon={DatabaseIcon}
                                title={t('erd.empty.none')}
                                description={t('erd.empty.none_description')}
                                className="h-full"
                                actions={canCreateTable ? [
                                    {
                                        label: t('tables.create'),
                                        icon: PlusIcon,
                                        onClick: () =>
                                            setDesignedTable({ table: null }),
                                    },
                                ] : []}
                            />
                        ) : !shownSchema.length ? (
                            <EmptyState
                                icon={EyeOffIcon}
                                title={t('erd.empty.hidden')}
                                description={t('erd.empty.hidden_description')}
                                className="h-full"
                                actions={[
                                    {
                                        label: t('erd.visibility.show_all'),
                                        variant: 'outline',
                                        onClick: () =>
                                            setHiddenTables(new Set()),
                                    },
                                ]}
                            />
                        ) : (
                            <SchemaTableMenuContext.Provider value={tableMenu}>
                                <SchemaCanvas
                                    key={layoutRequest}
                                    schema={shownSchema}
                                    detail={detail}
                                    // The isolated tables keep their compact layout, and their places are not saved.
                                    savedPositions={
                                        isolatedTable ? undefined : positions
                                    }
                                    onPositionsChange={
                                        isolatedTable
                                            ? undefined
                                            : movePositions
                                    }
                                    showCardinality={showCardinality}
                                    colorBy={colorBy}
                                    minimap={showMinimap}
                                    findingsByTable={findingCounts}
                                    implicitRelationships={implicitLinks}
                                    onConnectColumns={connectColumns}
                                    keepLayout={!isolatedTable}
                                    centeredTable={centeredTable}
                                    selectedTable={selectedTable}
                                    onTableSelect={setSelectedTable}
                                    zoomOnScroll
                                    aside={
                                        inspectedTable && (
                                            <TableInspector
                                                table={inspectedTable}
                                                schema={schema}
                                                findings={diagnostics?.findings.filter(
                                                    (finding) =>
                                                        finding.table ===
                                                        inspectedTable.name,
                                                )}
                                                onCreateRelation={(finding) =>
                                                    finding.references &&
                                                    setNewRelation({
                                                        source: {
                                                            table: finding.table,
                                                            column: finding.column,
                                                        },
                                                        target: finding.references,
                                                    })
                                                }
                                                onShowTable={showTable}
                                                onClose={() =>
                                                    setSelectedTable(null)
                                                }
                                                actions={
                                                    <>
                                                        <Button
                                                            variant="outline"
                                                            size="xs"
                                                            onClick={() =>
                                                                openData(
                                                                    inspectedTable.name,
                                                                )
                                                            }
                                                        >
                                                            <Table2Icon />
                                                            {t(
                                                                'erd.actions.open_data',
                                                            )}
                                                        </Button>
                                                        <Button
                                                            variant="outline"
                                                            size="xs"
                                                            onClick={() =>
                                                                void newQuery(
                                                                    inspectedTable.name,
                                                                )
                                                            }
                                                        >
                                                            <Code2Icon />
                                                            {t(
                                                                'erd.actions.new_query',
                                                            )}
                                                        </Button>
                                                        {isolatedTable !==
                                                            inspectedTable.name && (
                                                            <Button
                                                                variant="outline"
                                                                size="xs"
                                                                onClick={() =>
                                                                    isolate(
                                                                        inspectedTable.name,
                                                                    )
                                                                }
                                                            >
                                                                <FocusIcon />
                                                                {t(
                                                                    'erd.focus.show_neighbours',
                                                                )}
                                                            </Button>
                                                        )}
                                                        {canAlter(inspectedTable.name) && (
                                                            <Button
                                                                variant="outline"
                                                                size="xs"
                                                                onClick={() =>
                                                                    setDesignedTable(
                                                                        {
                                                                            table: inspectedTable.name,
                                                                        },
                                                                    )
                                                                }
                                                            >
                                                                <PencilRulerIcon />
                                                                {t(
                                                                    'tables.workspace.edit_structure',
                                                                )}
                                                            </Button>
                                                        )}
                                                        <Button
                                                            variant="outline"
                                                            size="xs"
                                                            onClick={() =>
                                                                askChat(
                                                                    t(
                                                                        'tables.workspace.ask_table',
                                                                        {
                                                                            table: inspectedTable.name,
                                                                        },
                                                                    ),
                                                                )
                                                            }
                                                        >
                                                            <MessageSquareIcon />
                                                            {t(
                                                                'erd.ai.ask_table',
                                                            )}
                                                        </Button>
                                                    </>
                                                }
                                            />
                                        )
                                    }
                                />
                            </SchemaTableMenuContext.Provider>
                        )}
                    </div>
                </ResizablePanel>
                {isAssistantOpen && (
                    <>
                        <ResizableHandle className="bg-transparent" />
                        <ResizablePanel
                            id="erd-assistant"
                            defaultSize="380px"
                            minSize="300px"
                            maxSize="50%"
                            className="flex min-w-0 flex-col overflow-hidden rounded-lg border bg-background"
                        >
                            <ErdAssistantPanel
                                assistant={assistant}
                                onClose={() => setIsAssistantOpen(false)}
                            />
                        </ResizablePanel>
                    </>
                )}
            </ResizablePanelGroup>

            {newRelation && (
                <AddForeignKeyDialog
                    projectId={project.id}
                    source={newRelation.source}
                    target={newRelation.target}
                    schema={schema}
                    onClose={() => setNewRelation(null)}
                    onDone={() => {
                        const table = newRelation.source.table;

                        setNewRelation(null);
                        reloadSchema();
                        showTable(table);
                    }}
                />
            )}

            {designedTable && (
                <TableDesigner
                    projectId={project.id}
                    driver={driver}
                    tables={tableNames}
                    table={designedTable.table}
                    onClose={() => setDesignedTable(null)}
                    onDone={(name) => {
                        setDesignedTable(null);
                        reloadSchema();
                        showTable(name);
                    }}
                />
            )}
        </>
    );
}
