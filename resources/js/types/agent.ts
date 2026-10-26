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
    Route,
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
};

export function getMetadata(name: AgentToolNameType): ToolMetadata | undefined {
    switch (name) {
        case AgentTools.DatabaseSchema:
            return { icon: Database, color: 'text-purple-500' };
        case AgentTools.ListTables:
            return { icon: Table2, color: 'text-blue-500' };
        case AgentTools.ShowSchemaDiagram:
            return { icon: Workflow, color: 'text-indigo-500' };
        case AgentTools.CreateRecord:
            return { icon: FilePlus2, color: 'text-green-500' };
        case AgentTools.FocusTable:
            return { icon: Focus, color: 'text-cyan-500' };
        case AgentTools.ReadQuery:
            return { icon: Search, color: 'text-sky-500' };
        case AgentTools.UpdateProjectMemory:
            return { icon: HardDriveDownloadIcon, color: 'text-green-500' };
        case AgentTools.WriteQuery:
            return { icon: Pencil, color: 'text-orange-500' };
        case AgentTools.ShowQueryResults:
            return { icon: Sheet, color: 'text-emerald-500' };
        case AgentTools.ShowKpis:
            return { icon: Gauge, color: 'text-amber-500' };
        case AgentTools.ShowChart:
            return { icon: ChartColumnBig, color: 'text-pink-500' };
        case AgentTools.ProfileTable:
            return { icon: ScanSearch, color: 'text-teal-500' };
        case AgentTools.ShowRecord:
            return { icon: IdCard, color: 'text-violet-500' };
        case AgentTools.ExplainQuery:
            return { icon: Route, color: 'text-red-500' };
        case AgentTools.ExportDatabase:
            return { icon: FileArchive, color: 'text-lime-600' };
        case AgentTools.SendMail:
            return { icon: Mail, color: 'text-blue-600' };
        case AgentTools.ManageMailRecipients:
            return { icon: UserCheck, color: 'text-blue-600' };
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

export type ExplainQueryToolUIPart = CustomOutputToolUIPart<{
    steps: string[];
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
    tables: string[] | 'all';
    bytes: number;
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

export type PlanNode = {
    label: string;
    detail: string | null;
    cost: number | null;
    rows: number | null;
    warning: string | null;
    children: PlanNode[];
};

export type WritePreview = {
    query: string;
    affectedRows: number | null;
};
