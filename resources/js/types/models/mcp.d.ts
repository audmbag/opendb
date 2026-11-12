import type { BaseModel } from '@/types/models/base-model';
import type { Project } from '@/types/models/project';

export interface McpToken extends BaseModel {
    name: string;
    project_id: string;
    project: Pick<Project, 'id' | 'name'> | null;
    last_used_at: string | null;
}

/** A token just issued: the only response that holds its value in clear and the entry of the MCP client. */
export interface IssuedMcpToken {
    data: McpToken;
    token: string;
    config: Record<string, unknown>;
}
