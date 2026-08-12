import React, { useCallback } from 'react';
import { Suggestion, Suggestions } from '@/components/ai-elements/suggestion';
import { shuffle } from '@/lib/utils';

const suggestions = shuffle([
    {
        icon: '🗺️',
        prompt: "Donne-moi une vue d'ensemble de la base : tables, tailles et relations.",
    },
    {
        icon: '🐢',
        prompt: 'Quelles sont les requêtes les plus lentes en ce moment ?',
    },
    {
        icon: '⚡',
        prompt: 'Quels index manquent pour améliorer les performances ?',
    },
    {
        icon: '🔗',
        prompt: 'Y a-t-il des clés étrangères sans contrainte déclarée ?',
    },
    {
        icon: '🚨',
        prompt: 'Détecte les anomalies dans les données (doublons, NULL suspects, incohérences).',
    },
    { icon: '🗂️', prompt: 'Génère le diagramme ERD complet de la base.' },
    {
        icon: '🧹',
        prompt: 'Quelles colonnes semblent inutilisées et peuvent être supprimées ?',
    },
    {
        icon: '🌱',
        prompt: 'Génère des données de test réalistes pour les tables principales.',
    },
    {
        icon: '♻️',
        prompt: 'Y a-t-il des index redondants que je peux supprimer sans risque ?',
    },
    { icon: '📝', prompt: 'Documente le schéma de la base en Markdown.' },
    {
        icon: '📊',
        prompt: 'Montre-moi les statistiques de taille et de fragmentation par table.',
    },
]);

export default function PromptSuggestions({ onClick } : { onClick?: (value: string) => void }) {

    const handleClick = useCallback((prompt: string) => {
        onClick?.(prompt);
    }, [onClick])

    return (
        <Suggestions className={'py-4'}>
            {suggestions.map((suggestion) => (
                <Suggestion
                    key={suggestion.prompt}
                    onClick={() => handleClick(suggestion.prompt)}
                    suggestion={suggestion.prompt}
                    className="gap-2 rounded-lg"
                >
                    <span>{suggestion.icon}</span>
                    <span>{suggestion.prompt}</span>
                </Suggestion>
            ))}
        </Suggestions>
    );
}
