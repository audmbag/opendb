import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import i18n from 'laravel-react-i18n/vite';
import path from 'path';
import { defineConfig } from 'vite';
import reactCall from 'react-call/vite';
import shikiLanguages from './build/shiki-languages';

export default defineConfig(({ command }) => ({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            refresh: true,
        }),
        // The pages are only ever rendered in the browser: no server rendering, in development as in production.
        inertia({ ssr: false }),
        react({
            babel: {
                plugins: ['babel-plugin-react-compiler'],
            },
        }),
        tailwindcss(),
        wayfinder({
            formVariants: true,
            path: "resources/js/wayfinder",
        }),
        // It only names the callable components for the devtools, in development: a build would call it on every module for nothing.
        command === 'serve' && reactCall(),
        i18n(),
        shikiLanguages(),
    ],
    server: {
        watch: {
            // The copies of the application the build prepares hold thousands of files nobody edits: watching them
            // exhausts the watchers the system allows.
            ignored: ['**/build/dist/**', '**/build/cache/**'],
        },
    },
    build: {
        // Served from the machine itself, a chunk weighs on parsing rather than on the network: the conversation (KaTeX,
        // Mermaid) and the SQL editor (CodeMirror) need up to 1 MB, the grammars of Shiki are loaded on demand. Beyond, a
        // chunk deserves to be split.
        chunkSizeWarningLimit: 1024,
    },
}));
