import type { DriverConfig } from '@/types/databases';
import type { BaseModel } from '@/types/models/base-model';

export interface Project extends BaseModel {
    name: string;
    description: string;
    path: string;
    driver_config?: DriverConfig;
    created_at_diff_for_humans: string;
    status: 'draft' | 'inactive';
}
