import type { BaseModel } from '@/types/models/base-model';

export interface User extends BaseModel<number> {
    name: string
}
