import type { DatabaseConfig, DatabaseType } from "../databases";



export type RecentProject = {
    id: string;
    name: string;
    description: string;
    createdAt: string;
    databaseConnexion: string;
    path: string;
    database: DatabaseConfig
}