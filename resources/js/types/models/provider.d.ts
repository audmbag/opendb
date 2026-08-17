import type { BaseModel } from '@/types/models/base-model';

export interface AiModel {
    id: string;
    label: string;
    active: boolean;
    context_window: number;
}

export interface Provider extends BaseModel {
    label: string;
    icon: string;
    api_key?: string;
    api_url?: string;
    models: AiModel[];
    custom_openai_compatible: boolean;
    is_connected: boolean;
}
