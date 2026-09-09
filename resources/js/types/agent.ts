import type { LucideIcon } from 'lucide-react';
import {
    Database,
    Table2,
    Workflow,
    FilePlus2,
    Focus,
    Search,
    Pencil,
} from 'lucide-react';
import type { ToolUIPart } from 'ai';

export const AgentTools = {
     DatabaseSchema: 'tool-DatabaseSchema',
     ListTables: 'tool-ListTables',
     ShowSchemaDiagram: 'tool-ShowSchemaDiagram',
     CreateRecord: 'tool-CreateRecord',
     FocusTable: 'tool-FocusTable',
     ReadQuery: 'tool-ReadQuery',
     WriteQuery: 'tool-WriteQuery'
} as const;



export const ArtifactableTools = [
    AgentTools.ShowSchemaDiagram,
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
        case AgentTools.WriteQuery:
            return { icon: Pencil, color: 'text-orange-500' };
    }
}



// Custom outputs

export type CustomOutputToolUIPart<T = {}> = Omit<ToolUIPart, 'output'> & {
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
