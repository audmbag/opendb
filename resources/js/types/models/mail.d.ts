import type { BaseModel } from '@/types/models/base-model';

export type MailEncryption = 'tls' | 'ssl';

export interface MailSetting extends BaseModel<number> {
    host: string;
    port: number;
    encryption: MailEncryption | null;
    username: string | null;
    has_password: boolean;
    from_address: string;
    from_name: string | null;
}

export interface MailRecipient extends BaseModel<number> {
    email: string;
    name: string | null;
}
