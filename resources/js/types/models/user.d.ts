import type { BaseModel } from '@/types/models/base-model';

export type AgentAccessModeSetting = 'read-only' | 'read-write';

export type AgentEffortSetting = 'default' | 'high';

/**
 * When the changes of structure asked by the assistant of the diagram run.
 */
export type SchemaApplyModeSetting = 'draft' | 'additions' | 'auto';

export type ExportFormatSetting = 'sql' | 'csv';

export type ExportCompressionSetting = 'none' | 'zip' | 'gzip' | 'tar';

/**
 * The global settings of the user, saved on the server and read by the agent.
 */
export interface UserSettings {
    agent: {
        accessMode: AgentAccessModeSetting;
        effort: AgentEffortSetting;
        maxSteps: number;
        maxRows: number;
        schemaApplyMode: SchemaApplyModeSetting;
    };
    export: {
        format: ExportFormatSetting;
        compression: ExportCompressionSetting;
    };
}

export interface User extends BaseModel<number> {
    name: string;
    settings: UserSettings;
}
