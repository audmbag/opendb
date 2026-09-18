import type { BaseModel } from '@/types/models/base-model';

export enum DefaultDrivers {
    MYSQL = 'mysql',
    SQLITE = 'sqlite',
    MARIADB = 'mariadb',
    POSTGRESQL = 'pgsql',
}

export type Driver = {
    id: DefaultDrivers;
    name: string;
    default_host?: string;
    default_port?: number;
    default_username?: string;
    icon?: string;
};


export interface SSHConfiguration {
    host: string;
    port: number;
    user: string;
    id_file: string;
}



export interface Table {
    name: string;
    columns: number;
    enabled: boolean;
}

export interface DriverConfig extends BaseModel<number> {
    driver?: Driver;
    driver_id?: string;
    host?: string;
    port?: number;
    database: string;
    username?: string;
    password?: string;
    connection_string?: string;
    ssh?: SSHConfiguration;
    tables_info?: Table[];
}
