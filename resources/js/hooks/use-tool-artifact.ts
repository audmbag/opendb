import { useHttp } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { useInView } from 'react-intersection-observer';

/**
 * Delays before asking again for an artifact whose tool call is not saved yet: the message is stored right after the stream ends.
 */
const RETRY_DELAYS = [1000, 2000, 4000];

const UNAVAILABLE_MESSAGE = "Impossible de charger l'aperçu.";

/**
 * Load the data of an artifact from ToolArtifactController once it scrolls into view, and again whenever its URL changes.
 */
export function useToolArtifact<T>(url: string, enabled = true) {
    const [data, setData] = useState<T | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [attempt, setAttempt] = useState(0);

    const { get, processing } = useHttp<null, { data: T }>();

    const { ref, inView } = useInView({
        triggerOnce: true,
        threshold: 0.1,
    });

    useEffect(() => {
        if (!inView || !enabled) {
            return;
        }

        get(url, {
            onSuccess: (response) => {
                setError(null);
                setData(response.data);
            },
            onError: (errors) => setError(Object.values(errors)[0] ?? UNAVAILABLE_MESSAGE),
            onHttpException: (response) => {
                if (response.status === 404 && attempt < RETRY_DELAYS.length) {
                    setTimeout(() => setAttempt(attempt + 1), RETRY_DELAYS[attempt]);

                    return;
                }

                setError(UNAVAILABLE_MESSAGE);
            },
            onNetworkError: () => setError(UNAVAILABLE_MESSAGE),
        }).catch(() => null);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [inView, enabled, url, attempt]);

    return { ref, data, error, processing: processing || (data === null && error === null) };
}
