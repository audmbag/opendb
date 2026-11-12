import { http } from '@inertiajs/react';
import { trans } from '@/lib/i18n';

/**
 * A request of the Tables tab that failed, with the message to show and the validation errors keyed by field.
 */
export class TableRequestError extends Error {
    constructor(
        message: string,
        public readonly errors: Record<string, string[]> = {},
        public readonly status = 0,
        public readonly cancelled = false,
        /**
         * The statement the database refused, when a change of structure fails.
         */
        public readonly statement: string | null = null,
    ) {
        super(message);
    }

    /**
     * The first message of the given field, or of any field when none is given.
     */
    first(field?: string): string {
        return (
            (field
                ? this.errors[field]?.[0]
                : Object.values(this.errors)[0]?.[0]) ?? this.message
        );
    }
}

/**
 * The `data` of a JSON endpoint of the Tables tab. Unlike useHttp, several requests can run at once and each one
 * can be cancelled by its own signal: the grid cancels the page it no longer needs when the view changes.
 *
 * @throws TableRequestError
 */
export async function requestTableData<T>(
    method: 'get' | 'post' | 'put' | 'delete',
    url: string,
    options: { data?: unknown; signal?: AbortSignal } = {},
): Promise<T> {
    try {
        const response = await http.getClient().request({
            method,
            url,
            data: options.data,
            signal: options.signal,
            headers: { Accept: 'application/json' },
        });

        return (JSON.parse(response.data) as { data: T }).data;
    } catch (error) {
        throw toRequestError(error);
    }
}

function toRequestError(error: unknown): TableRequestError {
    if (error instanceof Error && error.name === 'HttpCancelledError') {
        return new TableRequestError(trans('tables.errors.cancelled'), {}, 0, true);
    }

    if (error instanceof Error && 'response' in error) {
        const response = (
            error as Error & { response: { status: number; data: string } }
        ).response;
        let body: {
            message?: string;
            errors?: Record<string, string[]>;
            statement?: string;
        } = {};

        try {
            body = JSON.parse(response.data);
        } catch {
            // An HTML error page: the status is all there is to tell.
        }

        const message =
            body.message ??
            (response.status === 404
                ? trans('tables.errors.table_missing')
                : trans('tables.errors.status', { status: response.status }));

        return new TableRequestError(
            message,
            body.errors ?? {},
            response.status,
            false,
            body.statement || null,
        );
    }

    return new TableRequestError(trans('tables.errors.unreachable'));
}
