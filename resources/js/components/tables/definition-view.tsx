import { useEffect, useState } from 'react';
import {
    CodeBlockActions,
    CodeBlockCopyButton,
    CodeBlockHeader,
    CodeBlockTitle,
} from '@/components/code-block';
import Alert from '@/components/alert';
import { Skeleton } from '@/components/ui/skeleton';
import TableBrowserController from '@/wayfinder/actions/App/Http/Controllers/Tables/TableBrowserController';
import type { TableRequestError } from './api';
import { requestTableData } from './api';
import SqlCodeBlock from './sql-code-block';

/**
 * The statement that creates the table, as the database describes it, to copy into a migration or another database.
 */
export default function DefinitionView({
    projectId,
    table,
}: {
    projectId: string;
    table: string;
}) {
    const [definition, setDefinition] = useState<{
        table: string;
        sql: string;
    } | null>(null);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        const controller = new AbortController();

        requestTableData<{ sql: string }>(
            'get',
            TableBrowserController.definition.url({
                project: projectId,
                table: encodeURIComponent(table),
            }),
            { signal: controller.signal },
        )
            .then(({ sql }) => setDefinition({ table, sql }))
            .catch(
                (exception: TableRequestError) =>
                    !exception.cancelled && setError(exception.first()),
            );

        return () => controller.abort();
    }, [projectId, table]);

    if (error) {
        return (
            <Alert
                variant="destructive"
                title="Le DDL n'a pas pu être lu"
                message={error}
                className="m-4 w-auto"
            />
        );
    }

    if (definition?.table !== table) {
        return <Skeleton className="m-4 h-64" />;
    }

    return (
        <div className="min-h-0 flex-1 overflow-y-auto p-4">
            <SqlCodeBlock
                code={definition.sql}
                className="[&_pre]:whitespace-pre-wrap"
            >
                <CodeBlockHeader>
                    <CodeBlockTitle>CREATE TABLE {table}</CodeBlockTitle>
                    <CodeBlockActions>
                        <CodeBlockCopyButton />
                    </CodeBlockActions>
                </CodeBlockHeader>
            </SqlCodeBlock>
        </div>
    );
}
