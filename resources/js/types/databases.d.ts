


export type DatabaseType = 'mysql' | 'postgresql' | 'sqlite' | 'mariadb';


export type DatabaseConfig = {
    type: DatabaseType;
    label: string;
    host?: string;
    port?: number;
    databaseName?: string;
    databasePath?: string;
};