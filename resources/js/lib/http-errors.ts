import { toast } from 'sonner';
import { trans } from '@/lib/i18n';

type FailedResponse = { status: number; data: unknown };

/**
 * The message the server gave with a failed response, like the refusal of a privilege, or null when it gave none.
 */
export function responseMessage(response: Pick<FailedResponse, 'data'>): string | null {
    try {
        const body = typeof response.data === 'string' ? JSON.parse(response.data) : response.data;

        return typeof body?.message === 'string' && body.message !== '' ? body.message : null;
    } catch {
        return null;
    }
}

/**
 * A request refused by the privileges of the user and left unhandled by its caller still tells why: the refusal is
 * shown once, with the words of the server.
 */
export function initializeForbiddenRequestToasts(): void {
    if (typeof window === 'undefined') {
        return;
    }

    window.addEventListener('unhandledrejection', (event) => {
        const response = (event.reason as { response?: FailedResponse } | null)?.response;

        if (response?.status !== 403) {
            return;
        }

        event.preventDefault();
        toast.error(trans('common.forbidden'), {
            id: 'forbidden-request',
            description: responseMessage(response) ?? trans('common.unexpected_error'),
        });
    });
}
