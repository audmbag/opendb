import { trans } from '@/lib/i18n';
import { TableRequestError } from './api';

const xsrfToken = (): string | null => {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : null;
};

/**
 * Send the form and save the file the server answers with, under the name it gives. An error of the server is
 * thrown with its message instead of being saved as a file.
 *
 * @throws TableRequestError
 */
export async function downloadFile(
    url: string,
    data: unknown,
): Promise<string> {
    const token = xsrfToken();
    const response = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...(token ? { 'X-XSRF-TOKEN': token } : {}),
        },
        body: JSON.stringify(data),
    }).catch(() => {
        throw new TableRequestError(
            trans('tables.errors.unreachable'),
        );
    });

    if (!response.ok) {
        const body = (await response.json().catch(() => ({}))) as {
            message?: string;
            errors?: Record<string, string[]>;
        };

        throw new TableRequestError(
            body.message ??
                trans('tables.errors.status', { status: response.status }),
            body.errors ?? {},
            response.status,
        );
    }

    const disposition = response.headers.get('Content-Disposition') ?? '';
    const name = decodeURIComponent(
        /filename\*=UTF-8''([^;]+)/i.exec(disposition)?.[1] ??
            /filename="?([^";]+)"?/i.exec(disposition)?.[1] ??
            'export',
    );
    const link = document.createElement('a');

    link.href = URL.createObjectURL(await response.blob());
    link.download = name;
    link.click();
    setTimeout(() => URL.revokeObjectURL(link.href), 60_000);

    return name;
}
