import { useLaravelReactI18n } from 'laravel-react-i18n';
import { TextSearchIcon } from 'lucide-react';
import TableAssistantController from '@/wayfinder/actions/App/Http/Controllers/Tables/TableAssistantController';
import type { FilterSuggestion } from '../types';
import AiPromptInput from './ai-prompt-input';
import { useTableAssistant } from './use-table-assistant';

type Props = {
    projectId: string;
    table: string;
    disabledReason: string | null;
    onSuggestion: (suggestion: FilterSuggestion) => void;
};

/**
 * A filter described in plain language, turned by the assistant into filters the user can still edit before reading on.
 */
export default function AiFilterInput({
    projectId,
    table,
    disabledReason,
    onSuggestion,
}: Props) {
    const { t } = useLaravelReactI18n();
    const { ask, error, processing } = useTableAssistant<FilterSuggestion>();

    const suggest = async (prompt: string): Promise<boolean> => {
        const suggestion = await ask(
            TableAssistantController.filter.url({
                project: projectId,
                table: encodeURIComponent(table),
            }),
            { prompt },
        );

        if (suggestion) {
            onSuggestion(suggestion);
        }

        // The sentence stays in the field: the user often refines it.
        return false;
    };

    return (
        <AiPromptInput
            icon={TextSearchIcon}
            placeholder={t('tables.assistant.filter_placeholder')}
            label={t('tables.assistant.suggest_filter')}
            onSubmit={suggest}
            processing={processing}
            error={error}
            disabledReason={disabledReason}
        />
    );
}
