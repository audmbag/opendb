import type { ToolUIPart } from 'ai';
import type { LucideIcon } from 'lucide-react';
import { HardDriveDownloadIcon } from 'lucide-react';
import {
    ChartColumnBig,
    Database,
    FileArchive,
    Table2,
    Workflow,
    FilePlus2,
    Focus,
    Gauge,
    Mail,
    IdCard,
    ScanSearch,
    Search,
    Pencil,
    Sheet,
    UserCheck,
} from 'lucide-react';

export const AgentTools = {
    DatabaseSchema: 'tool-DatabaseSchema',
    ListTables: 'tool-ListTables',
    ShowSchemaDiagram: 'tool-ShowSchemaDiagram',
    CreateRecord: 'tool-CreateRecord',
    UpdateProjectMemory: 'tool-UpdateProjectMemory',
    FocusTable: 'tool-FocusTable',
    ReadQuery: 'tool-ReadQuery',
    WriteQuery: 'tool-WriteQuery',
    ShowQueryResults: 'tool-ShowQueryResults',
    ShowKpis: 'tool-ShowKpis',
    ShowChart: 'tool-ShowChart',
    ProfileTable: 'tool-ProfileTable',
    ShowRecord: 'tool-ShowRecord',
    ExplainQuery: 'tool-ExplainQuery',
    ExportDatabase: 'tool-ExportDatabase',
    SendMail: 'tool-SendMail',
    ManageMailRecipients: 'tool-ManageMailRecipients',
} as const;



export const ArtifactableTools = [
    AgentTools.ShowSchemaDiagram,
    AgentTools.ShowQueryResults,
    AgentTools.ShowKpis,
    AgentTools.ShowChart,
    AgentTools.ProfileTable,
    AgentTools.ShowRecord,
    AgentTools.ExplainQuery,
    AgentTools.ExportDatabase,
];

export type AgentToolNameType = `tool-${keyof typeof AgentTools}`;

export type ToolMetadata = {
    icon: LucideIcon;
    color: string;
    /**
     * What the processing indicator tells the user while the tool is running.
     */
    activityLabel: string;
};

export function getMetadata(name: AgentToolNameType): ToolMetadata | undefined {
    switch (name) {
        case AgentTools.DatabaseSchema:
            return { icon: Database, color: 'text-purple-500', activityLabel: 'Lecture du schéma' };
        case AgentTools.ListTables:
            return { icon: Table2, color: 'text-blue-500', activityLabel: 'Récupération des tables' };
        case AgentTools.ShowSchemaDiagram:
            return { icon: Workflow, color: 'text-indigo-500', activityLabel: 'Création du diagramme' };
        case AgentTools.CreateRecord:
            return { icon: FilePlus2, color: 'text-green-500', activityLabel: "Création de l'enregistrement" };
        case AgentTools.FocusTable:
            return { icon: Focus, color: 'text-cyan-500', activityLabel: 'Inspection de la table' };
        case AgentTools.ReadQuery:
            return { icon: Search, color: 'text-sky-500', activityLabel: 'Exécution de la requête' };
        case AgentTools.UpdateProjectMemory:
            return { icon: HardDriveDownloadIcon, color: 'text-green-500', activityLabel: 'Mise à jour de la mémoire du projet' };
        case AgentTools.WriteQuery:
            return { icon: Pencil, color: 'text-orange-500', activityLabel: 'Modification des données' };
        case AgentTools.ShowQueryResults:
            return { icon: Sheet, color: 'text-emerald-500', activityLabel: 'Préparation des résultats' };
        case AgentTools.ShowKpis:
            return { icon: Gauge, color: 'text-amber-500', activityLabel: 'Calcul des indicateurs' };
        case AgentTools.ShowChart:
            return { icon: ChartColumnBig, color: 'text-pink-500', activityLabel: 'Création du graphique' };
        case AgentTools.ProfileTable:
            return { icon: ScanSearch, color: 'text-teal-500', activityLabel: 'Analyse de la table' };
        case AgentTools.ShowRecord:
            return { icon: IdCard, color: 'text-violet-500', activityLabel: "Chargement de l'enregistrement" };
        case AgentTools.ExplainQuery:
            return { icon: Workflow, color: 'text-red-500', activityLabel: "Analyse du plan d'exécution" };
        case AgentTools.ExportDatabase:
            return { icon: FileArchive, color: 'text-lime-600', activityLabel: 'Export de la base de données' };
        case AgentTools.SendMail:
            return { icon: Mail, color: 'text-blue-600', activityLabel: "Envoi de l'e-mail" };
        case AgentTools.ManageMailRecipients:
            return { icon: UserCheck, color: 'text-blue-600', activityLabel: 'Mise à jour des destinataires' };
    }
}



// Custom outputs

export type CustomOutputToolUIPart<T = object> = Omit<ToolUIPart, 'output'> & {
    output: {
        success: boolean
    } & T;
};

export type ShemaDiagramToolUIPart = CustomOutputToolUIPart<{
    focusedTable?: string;
    withRelatedTables: boolean;
    displayedTables: string[];
    displayedTablesCount?: number;
}>;

export type QueryResultsToolUIPart = CustomOutputToolUIPart<{
    title: string | null;
    columns: string[];
    totalRows: number;
}>;

export type KpisToolUIPart = CustomOutputToolUIPart<{
    labels: string[];
    count: number;
}>;

export type ChartType = 'bar' | 'line' | 'area' | 'pie';

export type ChartToolUIPart = CustomOutputToolUIPart<{
    title: string | null;
    chartType: ChartType;
    xColumn: string;
    yColumns: string[];
    stacked: boolean;
    totalRows: number;
    truncated: boolean;
}>;

export type ProfileTableToolUIPart = CustomOutputToolUIPart<{
    table: string;
}>;

export type RecordToolUIPart = CustomOutputToolUIPart<{
    table: string;
    key: Record<string, unknown>;
}>;

export type QueryPlanErrorCode = 'not_select' | 'syntax_error' | 'unknown_table' | 'hidden_table' | 'unsupported_driver' | 'other';

/**
 * What the agent receives for an ExplainQuery call: the id of the stored plan, or the error preventing it.
 */
export type ExplainQueryToolUIPart = CustomOutputToolUIPart<{
    planId?: string;
    error?: QueryPlanErrorCode;
    message?: string;
}>;

export type ExportFormat = 'sql' | 'csv';

export type ExportCompression = 'zip' | 'tar' | 'gzip';

export type ExportDatabaseToolUIPart = CustomOutputToolUIPart<{
    /**
     * The path of the archive, relative to the directory of the project.
     */
    path: string;
    format: ExportFormat;
    output: ExportCompression;
    /**
     * The exported tables, or 'query' when the archive holds the result of a query.
     */
    tables: string[] | 'all' | 'query';
    /**
     * The number of exported rows, only when the archive holds the result of a query.
     */
    rows?: number;
    bytes: number;
    /**
     * When the archive was written, missing from the exports saved before this was returned.
     */
    modified_at?: string;
}>;



// Artifact data, loaded from ToolArtifactController

export type CellValue = string | number | boolean | null;

export type QueryResultsPage = {
    columns: string[];
    rows: Record<string, CellValue>[];
    total: number;
    page: number;
    perPage: number;
};

export type KpiFormat = 'number' | 'currency' | 'percent' | 'duration';

export type KpiCard = {
    label: string;
    format: KpiFormat;
    /**
     * The ISO 4217 code of the currency, given by the agent with the currency format.
     */
    currency: string | null;
    unit: string | null;
    value: CellValue;
    error: string | null;
};

export type ChartData = {
    rows: Record<string, CellValue>[];
    truncated: boolean;
};

export type ColumnProfile = {
    name: string;
    type: string;
    key: 'PK' | 'UQ' | 'FK' | null;
    kind: 'boolean' | 'numeric' | 'temporal' | 'text' | 'categorical' | 'other';
    nullCount: number;
    nullRatio: number;
    distinctCount: number | null;
    min: CellValue;
    max: CellValue;
    averageLength: number | null;
    minLength: number | null;
    maxLength: number | null;
    topValues: { value: CellValue; count: number }[];
};

export type TableProfile = {
    table: string;
    totalRows: number;
    sampleSize: number;
    columns: ColumnProfile[];
};

export type RecordKey = Record<string, CellValue>;

export type InspectedRecord = {
    table: string;
    primaryKey: string[];
    key: RecordKey;
    values: Record<string, CellValue>;
    outgoing: { columns: string[]; table: string; key: RecordKey }[];
    incoming: { table: string; columns: string[]; key: RecordKey; count: number }[];
};

export type PlanOperation =
    | 'result'
    | 'full_scan'
    | 'index_full_scan'
    | 'range_scan'
    | 'index_lookup'
    | 'unique_lookup'
    | 'nested_loop'
    | 'hash_join'
    | 'merge_join'
    | 'hash'
    | 'group'
    | 'sort'
    | 'distinct'
    | 'limit'
    | 'subquery'
    | 'materialize'
    | 'union'
    | 'other';

export type PlanAlertSeverity = 'danger' | 'warn';

export type PlanAlert = {
    code: string;
    severity: PlanAlertSeverity;
    message: string;
    table: string | null;
    columns: string[];
    status?: 'remaining' | 'new';
};

export type PlanNode = {
    id: string;
    parentId: string | null;
    operation: PlanOperation;
    label: string;
    subtitle: string | null;
    table: string | null;
    index: string | null;
    badge: string | null;
    /**
     * The rows read by a step that reads a table.
     */
    rowsExamined: number | null;
    /**
     * The rows sent to the parent step, null when the engine does not estimate them.
     */
    estimatedRows: number | null;
    /**
     * Shown on the edge to the parent when the rows are not estimated (« 1 ligne par product_id »).
     */
    outputLabel: string | null;
    cost: number | null;
    selfCost: number | null;
    costShare: number | null;
    condition: { label: string; value: string } | null;
    explanation: string;
    alerts: PlanAlert[];
    details: { label: string; value: string; mono?: boolean; tone?: 'danger' | 'good' }[];
    before: { label: string; rowsExamined: number | null; cost: number | null } | null;
};

export type PlanTotals = {
    cost: number | null;
    rowsRead: number;
    alerts: { danger: number; warn: number };
};

export type ClassicPlan = {
    columns: string[] | null;
    rows: CellValue[][] | null;
    text: string | null;
};

export type PlanAlertReference = { code: string; table: string | null };

export type QueryPlan = {
    planId: string;
    driver: 'mysql' | 'mariadb' | 'pgsql' | 'sqlite';
    driverLabel: string;
    query: string;
    bindings: Record<string, CellValue>;
    fingerprint: string;
    createdAt: string;
    hasCosts: boolean;
    totals: PlanTotals;
    nodes: PlanNode[];
    classic: ClassicPlan;
    raw: unknown;
    previous: { planId: string; createdAt: string; totals: PlanTotals; nodes: PlanNode[]; classic: ClassicPlan; raw: unknown } | null;
    comparison: {
        previousPlanId: string;
        costBefore: number | null;
        costAfter: number | null;
        costChange: number | null;
        rowsReadBefore: number;
        rowsReadAfter: number;
        resolvedAlerts: PlanAlertReference[];
        remainingAlerts: PlanAlertReference[];
        newAlerts: PlanAlertReference[];
    } | null;
};

export type WritePreview = {
    query: string;
    affectedRows: number | null;
};
