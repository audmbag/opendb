import { useHttp } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { xsrfToken } from '@/lib/xsrf';
import ProjectController from '@/wayfinder/actions/App/Http/Controllers/ProjectController';

/**
 * Avancement d'un clonage, tel que App\Services\Cloning\Workflow\CloningProgress::poll() le renvoie.
 */
export type CloningProgress = {
    phase: 'preparing' | 'schema' | 'remapping' | 'tables';
    table: string | null;
    tablePosition: number;
    tableCount: number;
    tableRows: number;
    tableExpectedRows: number | null;
    cancelling: boolean;
};

/**
 * Le serveur répond un objet vide une fois le clonage terminé, ou avant qu'il ne commence.
 */
type ProgressResponse = CloningProgress | Record<string, never>;

const isProgress = (
    response: ProgressResponse | undefined,
): response is CloningProgress => typeof response?.phase === 'string';

/**
 * La page lit l'avancement chaque seconde : le serveur arrête le clonage quand elle cesse de le faire.
 */
const POLL_INTERVAL_MS = 1000;

/**
 * Suit le clonage `cloningId` tant que `running` est vrai : son avancement, le temps écoulé et son annulation.
 * Quitter la page, la recharger ou fermer la fenêtre annule le clonage, comme le bouton Annuler.
 */
export function useCloningProgress(
    projectId: string | undefined,
    cloningId: string,
    running: boolean,
) {
    const [progress, setProgress] = useState<CloningProgress | null>(null);
    const [elapsedSeconds, setElapsedSeconds] = useState(0);
    const [cancelRequested, setCancelRequested] = useState(false);
    const { get } = useHttp<null, ProgressResponse>();

    const cancelUrl = projectId
        ? ProjectController.cancelCloning.url({ project: projectId, cloningId })
        : null;

    /**
     * Une requête `keepalive` : le navigateur l'envoie jusqu'au bout même quand la page se ferme.
     */
    const sendCancel = useCallback(() => {
        if (!cancelUrl) {
            return;
        }

        const token = xsrfToken();

        fetch(cancelUrl, {
            method: 'POST',
            keepalive: true,
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...(token ? { 'X-XSRF-TOKEN': token } : {}),
            },
        }).catch(() => null);
    }, [cancelUrl]);

    // Une annulation partie avant que le serveur ne commence à suivre le clonage serait oubliée : elle est renvoyée.
    const cancelRequestedRef = useRef(false);

    const cancel = useCallback(() => {
        cancelRequestedRef.current = true;
        setCancelRequested(true);
        sendCancel();
    }, [sendCancel]);

    useEffect(() => {
        if (!running || !projectId) {
            return;
        }

        let stopped = false;
        let timer: number | undefined;

        const poll = () => {
            get(
                ProjectController.cloningProgress.url({
                    project: projectId,
                    cloningId,
                }),
                {
                    onHttpException: () => false,
                    onNetworkError: () => false,
                },
            )
                .then((response) => {
                    if (stopped || !isProgress(response)) {
                        return;
                    }

                    setProgress(response);

                    if (cancelRequestedRef.current && !response.cancelling) {
                        sendCancel();
                    }
                })
                .catch(() => null)
                .finally(() => {
                    if (!stopped) {
                        timer = window.setTimeout(poll, POLL_INTERVAL_MS);
                    }
                });
        };

        poll();

        return () => {
            stopped = true;
            window.clearTimeout(timer);
        };
    }, [get, projectId, cloningId, running, sendCancel]);

    useEffect(() => {
        if (!running) {
            return;
        }

        const startedAt = Date.now();
        const timer = window.setInterval(
            () =>
                setElapsedSeconds(Math.floor((Date.now() - startedAt) / 1000)),
            1000,
        );

        return () => window.clearInterval(timer);
    }, [running]);

    useEffect(() => {
        if (!running) {
            return;
        }

        const warnBeforeLeaving = (event: BeforeUnloadEvent) =>
            event.preventDefault();

        window.addEventListener('beforeunload', warnBeforeLeaving);
        window.addEventListener('pagehide', sendCancel);

        return () => {
            window.removeEventListener('beforeunload', warnBeforeLeaving);
            window.removeEventListener('pagehide', sendCancel);
        };
    }, [running, sendCancel]);

    // Une navigation dans l'application démonte la fenêtre sans fermer la page.
    const runningRef = useRef(running);
    const sendCancelRef = useRef(sendCancel);

    useEffect(() => {
        runningRef.current = running;
        sendCancelRef.current = sendCancel;
    }, [running, sendCancel]);

    useEffect(
        () => () => {
            if (runningRef.current) {
                sendCancelRef.current();
            }
        },
        [],
    );

    return {
        progress,
        elapsedSeconds,
        cancel,
        isCancelling: cancelRequested || progress?.cancelling === true,
    };
}
