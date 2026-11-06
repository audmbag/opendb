import { useEffect, useRef, useState } from 'react';
import type { TableRequestError } from '@/components/tables/api';
import { requestTableData } from '@/components/tables/api';
import { useValueAsRef } from '@/hooks/use-value-as-ref';
import SqlFileController from '@/wayfinder/actions/App/Http/Controllers/SqlEditor/SqlFileController';
import type { SaveStatus, SqlFile } from './types';

/**
 * The pause in the typing after which the tab is written to its file.
 */
const SAVE_DELAY = 800;

const fileArgs = (project: string, name: string) => ({
    project,
    file: encodeURIComponent(name),
});

/**
 * The content of a .sql file, read when its tab opens and written back a moment after the user stops typing.
 * One write runs at a time, each one sending the version the previous one returned: a file changed outside the
 * editor is never overwritten without the user agreeing to it.
 */
export function useSqlFile(
    projectId: string,
    name: string,
    onSaved?: (size: number) => void,
    /**
     * The file as the editor just created it: it is not read again.
     */
    initial?: SqlFile,
) {
    const [sql, setSqlState] = useState<string | null>(initial?.sql ?? null);
    const [status, setStatus] = useState<SaveStatus>(
        initial ? 'saved' : 'loading',
    );
    const [error, setError] = useState<string | null>(null);
    // Read when a write is sent: the tab may have been renamed since the user typed.
    const nameRef = useValueAsRef(name);
    const onSavedRef = useValueAsRef(onSaved);
    const version = useRef<string | null>(initial?.version ?? null);
    const latest = useRef(initial?.sql ?? '');
    const saved = useRef(initial?.sql ?? '');
    const timer = useRef<number | null>(null);
    const writing = useRef(false);

    const save = async (force = false): Promise<void> => {
        if (timer.current !== null) {
            window.clearTimeout(timer.current);
            timer.current = null;
        }

        if (writing.current || (latest.current === saved.current && !force)) {
            return;
        }

        const content = latest.current;

        writing.current = true;
        setStatus('saving');

        try {
            const written = await requestTableData<{ version: string }>(
                'put',
                SqlFileController.update.url(
                    fileArgs(projectId, nameRef.current),
                ),
                {
                    data: {
                        sql: content,
                        version: force ? null : version.current,
                    },
                },
            );

            version.current = written.version;
            saved.current = content;
            onSavedRef.current?.(new Blob([content]).size);
            setError(null);
            setStatus(latest.current === content ? 'saved' : 'pending');
        } catch (exception) {
            const failure = exception as TableRequestError;

            setError(failure.first());
            setStatus(failure.status === 409 ? 'conflict' : 'error');

            return;
        } finally {
            writing.current = false;
        }

        // Typed while the file was being written: the rest is written at once.
        if (latest.current !== saved.current) {
            void save();
        }
    };

    const saveRef = useValueAsRef(save);

    /**
     * The file as it is on the disk. The first read starts in the loading state already.
     */
    const read = async (): Promise<void> => {
        try {
            const file = await requestTableData<SqlFile>(
                'get',
                SqlFileController.show.url(
                    fileArgs(projectId, nameRef.current),
                ),
            );

            version.current = file.version;
            latest.current = file.sql;
            saved.current = file.sql;
            setSqlState(file.sql);
            setError(null);
            setStatus('saved');
        } catch (exception) {
            setError((exception as TableRequestError).first());
            setStatus('error');
        }
    };

    const load = (): Promise<void> => {
        setStatus('loading');

        return read();
    };

    useEffect(() => {
        if (version.current === null) {
            void read();
        }
        // Read once: a rename keeps the content of the tab.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [projectId]);

    // What was typed just before the tab closes, or the page is hidden, is written without waiting for the pause.
    useEffect(() => {
        const flush = () => {
            if (document.visibilityState === 'hidden') {
                void saveRef.current();
            }
        };

        document.addEventListener('visibilitychange', flush);

        return () => {
            document.removeEventListener('visibilitychange', flush);
            void saveRef.current();
        };
    }, [saveRef]);

    const setSql = (next: string) => {
        latest.current = next;
        setSqlState(next);

        // A conflict waits for the user to choose: nothing is written meanwhile.
        if (status === 'conflict') {
            return;
        }

        setStatus(next === saved.current ? 'saved' : 'pending');

        if (timer.current !== null) {
            window.clearTimeout(timer.current);
        }

        timer.current = window.setTimeout(() => void save(), SAVE_DELAY);
    };

    return {
        sql,
        status,
        error,
        setSql,
        /**
         * Keep the content of the tab, replacing the file changed outside the editor.
         */
        overwrite: () => save(true),
        /**
         * Drop the content of the tab for the file as it is on the disk.
         */
        reload: load,
        /**
         * Write what is not written yet, before the file is renamed.
         */
        flush: () => save(),
    };
}
