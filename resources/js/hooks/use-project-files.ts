import { useHttp } from '@inertiajs/react';
import { useCallback, useRef, useState } from 'react';
import ProjectFileController from '@/wayfinder/actions/App/Http/Controllers/ProjectFileController';

/**
 * Durée pendant laquelle l'index des fichiers est réutilisé avant d'être rechargé.
 */
const STALE_AFTER_MS = 30_000;

const DEFAULT_LIMIT = 30;

interface ProjectFileEntry {
    path: string;
    name: string;
    directory: string;
    type: 'folder' | 'file';
}

const toEntry = (path: string): ProjectFileEntry => {
    const isFolder = path.endsWith('/');
    const trimmed = isFolder ? path.slice(0, -1) : path;
    const separatorIndex = trimmed.lastIndexOf('/');

    return {
        path,
        name: trimmed.slice(separatorIndex + 1),
        directory: separatorIndex === -1 ? '' : trimmed.slice(0, separatorIndex + 1),
        type: isFolder ? 'folder' : 'file',
    };
};

const compareEntries = (a: ProjectFileEntry, b: ProjectFileEntry): number => {
    if (a.type !== b.type) {
        return a.type === 'folder' ? -1 : 1;
    }

    return a.name.localeCompare(b.name, undefined, { sensitivity: 'base' });
};

/**
 * Indique si les caractères de `query` apparaissent dans l'ordre dans `value`.
 */
const isSubsequence = (query: string, value: string): boolean => {
    let index = 0;

    for (const character of value) {
        if (character === query[index]) {
            index++;
        }

        if (index === query.length) {
            return true;
        }
    }

    return false;
};

const getScore = (entry: ProjectFileEntry, query: string): number => {
    const name = entry.name.toLowerCase();
    const path = entry.path.toLowerCase();

    if (name === query) {
        return 100;
    }

    if (name.startsWith(query)) {
        return 80;
    }

    if (name.includes(query)) {
        return 60;
    }

    if (path.includes(query)) {
        return 40;
    }

    return isSubsequence(query, path) ? 20 : 0;
};

/**
 * Recherche les fichiers et dossiers à proposer pour une mention `@`, à la manière de Claude Code :
 * - sans recherche, le contenu de la racine du projet ;
 * - une recherche terminée par `/` liste le contenu de ce dossier ;
 * - sinon, une recherche floue sur le chemin, en privilégiant le nom du fichier.
 */
const searchProjectFiles = (paths: string[], query: string, limit: number = DEFAULT_LIMIT): ProjectFileEntry[] => {
    const normalizedQuery = query.toLowerCase();

    if (normalizedQuery === '' || normalizedQuery.endsWith('/')) {
        return paths
            .map(toEntry)
            .filter((entry) => entry.directory.toLowerCase() === normalizedQuery)
            .sort(compareEntries)
            .slice(0, limit);
    }

    return paths
        .map(toEntry)
        .map((entry) => ({ entry, score: getScore(entry, normalizedQuery) }))
        .filter(({ score }) => score > 0)
        .sort((a, b) => b.score - a.score || a.entry.path.length - b.entry.path.length || compareEntries(a.entry, b.entry))
        .slice(0, limit)
        .map(({ entry }) => entry);
};

/**
 * Retourne le texte saisi entre le dernier `@` et le curseur, ou `null` si le curseur n'est pas dans une mention.
 */
const getMentionQuery = (value: string, cursorPosition: number | null, trigger: string = '@'): string | null => {
    const position = cursorPosition ?? value.length;
    const triggerIndex = value.lastIndexOf(trigger, position - 1);

    if (triggerIndex === -1) {
        return null;
    }

    const query = value.slice(triggerIndex + trigger.length, position);

    return /\s/.test(query) ? null : query;
};

const useProjectFiles = (projectId?: string) => {
    const [paths, setPaths] = useState<string[] | null>(null);
    const loadedAtRef = useRef(0);

    const { get, processing } = useHttp<null, string[]>();

    const load = useCallback(() => {
        if (!projectId || processing || Date.now() - loadedAtRef.current < STALE_AFTER_MS) {
            return;
        }

        loadedAtRef.current = Date.now();

        get(ProjectFileController.url(projectId), {
            onHttpException: () => false,
            onNetworkError: () => false,
        })
            .then((response) => response && setPaths(response))
            .catch(() => {
                loadedAtRef.current = 0;
            });
    }, [get, processing, projectId]);

    return { paths, load, isLoading: processing && paths === null };
};

export { type ProjectFileEntry, getMentionQuery, searchProjectFiles, useProjectFiles };
