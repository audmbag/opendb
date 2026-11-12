import { useLaravelReactI18n } from 'laravel-react-i18n';
import React, { useCallback } from 'react';
import { Suggestion, Suggestions } from '@/components/ai-elements/suggestion';
import { shuffle } from '@/lib/utils';

const suggestions = shuffle([
    {
        icon: '🗺️',
        prompt: 'ai.suggestions.overview',
    },
    {
        icon: '🐢',
        prompt: 'ai.suggestions.slow_queries',
    },
    {
        icon: '⚡',
        prompt: 'ai.suggestions.missing_indexes',
    },
    {
        icon: '🔗',
        prompt: 'ai.suggestions.undeclared_foreign_keys',
    },
    {
        icon: '🚨',
        prompt: 'ai.suggestions.anomalies',
    },
    { icon: '🗂️', prompt: 'ai.suggestions.erd' },
    {
        icon: '🧹',
        prompt: 'ai.suggestions.unused_columns',
    },
    {
        icon: '🌱',
        prompt: 'ai.suggestions.test_data',
    },
    {
        icon: '♻️',
        prompt: 'ai.suggestions.redundant_indexes',
    },
    { icon: '📝', prompt: 'ai.suggestions.documentation' },
    {
        icon: '📊',
        prompt: 'ai.suggestions.fragmentation',
    },
]);

export default function PromptSuggestions({ onClick } : { onClick?: (value: string) => void }) {
    const { t } = useLaravelReactI18n();

    const handleClick = useCallback((prompt: string) => {
        onClick?.(prompt);
    }, [onClick])

    return (
        <Suggestions className={'py-4'}>
            {suggestions.map((suggestion) => (
                <Suggestion
                    key={suggestion.prompt}
                    onClick={() => handleClick(t(suggestion.prompt))}
                    suggestion={t(suggestion.prompt)}
                    className="gap-2 rounded-lg"
                >
                    <span>{suggestion.icon}</span>
                    <span>{t(suggestion.prompt)}</span>
                </Suggestion>
            ))}
        </Suggestions>
    );
}
