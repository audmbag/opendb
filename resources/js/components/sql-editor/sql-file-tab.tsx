import type { EditorView } from '@uiw/react-codemirror';
import { FileWarningIcon } from 'lucide-react';
import { useEffect } from 'react';
import Alert from '@/components/alert';
import { EmptyState } from '@/components/empty-state';
import Button from '@/components/form/button';
import QueryConsole from '@/components/tables/console/query-console';
import { Skeleton } from '@/components/ui/skeleton';
import type { SaveStatus, SqlFile } from './types';
import { useSqlFile } from './use-sql-file';

type Props = {
    projectId: string;
    name: string;
    driver: string;
    tables: string[];
    completion?: Record<string, string[]>;
    runUrl?: string;
    exportUrl?: string;
    explainUrl?: string;
    reviewUrl?: string;
    onSchemaChanged: () => void;
    onStatusChange: (status: SaveStatus) => void;
    onEditorReady: (view: EditorView) => void;
    /**
     * Receives what writes the content not written yet, called before the file is renamed.
     */
    onFlushReady: (flush: () => Promise<void>) => void;
    /**
     * The size of the file once written, shown in the list of files.
     */
    onSaved: (size: number) => void;
    /**
     * The file as the editor just created it, with the query it opens with.
     */
    initial?: SqlFile;
    onRan?: () => void;
};

/**
 * A tab of the editor: the console of the existing Tables tab, whose script is the content of a .sql file,
 * saved as the user types.
 */
export default function SqlFileTab({
    projectId,
    name,
    driver,
    tables,
    completion,
    runUrl,
    exportUrl,
    explainUrl,
    reviewUrl,
    onSchemaChanged,
    onStatusChange,
    onEditorReady,
    onFlushReady,
    onSaved,
    initial,
    onRan,
}: Props) {
    const file = useSqlFile(projectId, name, onSaved, initial);

    useEffect(() => {
        onStatusChange(file.status);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [file.status]);

    useEffect(() => {
        onFlushReady(file.flush);
    });

    if (file.sql === null) {
        return file.status === 'error' ? (
            <div className="flex flex-1 items-center justify-center p-6">
                <EmptyState
                    icon={FileWarningIcon}
                    title={`${name} n'a pas pu être ouvert`}
                    description={file.error ?? undefined}
                    actions={[
                        {
                            label: 'Réessayer',
                            variant: 'outline',
                            onClick: () => void file.reload(),
                        },
                    ]}
                />
            </div>
        ) : (
            <div className="flex flex-1 flex-col gap-2 p-3" aria-busy="true">
                <Skeleton className="h-4 w-2/3" />
                <Skeleton className="h-4 w-1/2" />
                <Skeleton className="h-4 w-3/5" />
            </div>
        );
    }

    return (
        <>
            {(file.status === 'conflict' || file.status === 'error') && (
                <Alert
                    variant="destructive"
                    title={
                        file.status === 'conflict'
                            ? 'Fichier modifié sur le disque'
                            : "L'enregistrement a échoué"
                    }
                    message={file.error ?? ''}
                    className="m-3 mb-0 w-auto"
                >
                    <div className="flex gap-2">
                        {file.status === 'conflict' ? (
                            <>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    onClick={() => void file.reload()}
                                >
                                    Recharger le fichier
                                </Button>
                                <Button
                                    size="sm"
                                    variant="ghost"
                                    onClick={() => void file.overwrite()}
                                >
                                    Garder ma version
                                </Button>
                            </>
                        ) : (
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={() => void file.flush()}
                            >
                                Réessayer
                            </Button>
                        )}
                    </div>
                </Alert>
            )}
            <QueryConsole
                projectId={projectId}
                driver={driver}
                tables={tables}
                completion={completion}
                sql={file.sql}
                onSqlChange={file.setSql}
                runUrl={runUrl}
                runData={{ file: name }}
                exportUrl={exportUrl}
                exportName={name}
                explainUrl={explainUrl}
                reviewUrl={reviewUrl}
                onRan={onRan}
                withLocalHistory={false}
                onSchemaChanged={onSchemaChanged}
                onEditorReady={onEditorReady}
            />
        </>
    );
}
