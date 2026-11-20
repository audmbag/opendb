/**
 * Types de la réponse de App\Services\Cloning\CloningResult::toArray()
 */

export type CloningStatus = 'success' | 'warning';

export type CloningLogLevel =
    | 'debug'
    | 'info'
    | 'notice'
    | 'warning'
    | 'error'
    | 'critical'
    | 'alert'
    | 'emergency';

export type CloningLogEvent = CloningLog['event'];

interface CloningLogBase {
    ts: string;
    level: CloningLogLevel;
}

export interface RunStartedLog extends CloningLogBase {
    event: 'run_started';
    source: string;
    target: string;
}

export interface TableTransferredLog extends CloningLogBase {
    event: 'table_transferred';
    table: string;
    rows: number;
    skipped: number;
}

export interface TableTransferFailedLog extends CloningLogBase {
    event: 'table_transfer_failed';
    table: string;
    reason: string | null;
}

export interface RunFinishedLog extends CloningLogBase {
    event: 'run_finished';
    success: boolean;
    rows: number;
}

export interface KeyMappingCleanupCompletedLog extends CloningLogBase {
    event: 'key_mapping_cleanup_completed';
}

export type CloningLog =
    | RunStartedLog
    | TableTransferredLog
    | TableTransferFailedLog
    | RunFinishedLog
    | KeyMappingCleanupCompletedLog;

export interface CloningStats {
    tables_transferred: number;
    tables_failed: number;
    tables_skipped: number;
    rows: number;
    rows_skipped: number;
    warnings: number;
    errors: number;
}

export interface CloningResult {
    status: CloningStatus;
    complete: boolean;
    successful: boolean | null;
    source: string | null;
    target: string | null;
    started_at: string | null;
    finished_at: string | null;
    duration: number | null;
    stats: CloningStats;
    logs: CloningLog[];
}


export interface CloningResultMinimalResult {
    /** Null pour un clonage annulé, qui n'écrit pas de journal. */
    logPath: string | null;
    status: CloningStatus | 'cancelled';
    successful: boolean;
}
