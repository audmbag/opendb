import type { Plugin } from 'vite';

/**
 * The languages of Shiki the build keeps: SQL and JSON, which the application highlights itself, and those the agent
 * is likely to write in its answers. A block in another language is shown as plain text, as Streamdown does with any
 * language it does not know.
 */
export const KEPT_LANGUAGES = [
    'sql', 'plsql', 'json', 'jsonc', 'json5', 'yaml', 'toml', 'ini', 'dotenv', 'xml', 'csv',
    'html', 'css', 'javascript', 'typescript', 'jsx', 'tsx', 'markdown', 'graphql', 'prisma',
    'python', 'php', 'java', 'kotlin', 'csharp', 'go', 'rust', 'c',
    'shellscript', 'powershell', 'bat', 'docker', 'diff', 'log', 'regexp',
];

/**
 * Keeps in the build only the languages of KEPT_LANGUAGES: Shiki bundles about 240 of them, a few megabytes the binary
 * would carry for nothing. The entries left out lose their import(), so that no chunk is made for them.
 */
export default function shikiLanguages(kept: string[] = KEPT_LANGUAGES): Plugin {
    const keptIds = new Set(kept);

    return {
        name: 'opendb:shiki-languages',
        apply: 'build',
        transform(code, id) {
            if (!/shiki\/dist\/langs-bundle-full-[^/]+\.mjs$/.test(id)) {
                return null;
            }

            const entry = /\t\{\n\t\t"id": "([^"]+)"[\s\S]*?\n\t\}/g;
            const entries = [...code.matchAll(entry)];
            const keptEntries = entries.filter(([, languageId]) => keptIds.has(languageId));
            const missing = kept.filter((languageId) => !entries.some(([, found]) => found === languageId));

            if (entries.length === 0 || missing.length > 0) {
                this.error(`Shiki languages not found in ${id}: ${missing.join(', ') || 'none at all'}`);
            }

            const start = entries[0].index;
            const last = entries[entries.length - 1];
            const end = last.index + last[0].length;

            return {
                code: code.slice(0, start) + keptEntries.map(([block]) => block).join(',\n') + code.slice(end),
                map: null,
            };
        },
    };
}
