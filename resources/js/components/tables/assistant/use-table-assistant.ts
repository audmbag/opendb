import { useRef, useState } from 'react';
import { useAgentSettingsStore } from '@/store/use-agent-settings-store';
import type { TableRequestError } from '../api';
import { requestTableData } from '../api';

const NO_MODEL_MESSAGE = "Choisissez un modèle d'IA pour utiliser l'assistant.";

/**
 * A request to one of the AI helpers, with the model chosen in the chat. A new question cancels the one still running.
 */
export function useTableAssistant<T>() {
    const [result, setResult] = useState<T | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [processing, setProcessing] = useState(false);
    const controller = useRef<AbortController | null>(null);

    const ask = async (
        url: string,
        data: Record<string, unknown> = {},
    ): Promise<T | null> => {
        const selectedModel = useAgentSettingsStore.getState().selectedModel;

        if (!selectedModel) {
            setError(NO_MODEL_MESSAGE);

            return null;
        }

        controller.current?.abort();
        const current = new AbortController();
        controller.current = current;

        setProcessing(true);
        setError(null);

        try {
            const response = await requestTableData<T>('post', url, {
                data: {
                    ...data,
                    provider: selectedModel.providerId,
                    model: selectedModel.modelId,
                },
                signal: current.signal,
            });

            setResult(response);

            return response;
        } catch (exception) {
            const requestError = exception as TableRequestError;

            if (!requestError.cancelled) {
                setError(
                    requestError.errors.provider || requestError.errors.model
                        ? NO_MODEL_MESSAGE
                        : requestError.first(),
                );
            }

            return null;
        } finally {
            // A newer question took over: it ends the loading state itself.
            if (controller.current === current) {
                setProcessing(false);
            }
        }
    };

    const reset = () => {
        controller.current?.abort();
        setResult(null);
        setError(null);
        setProcessing(false);
    };

    return { ask, result, error, processing, reset };
}
