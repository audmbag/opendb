/**
 * What the user left in progress in the project they are about to leave, told by the pages that hold it. Each page
 * describes its work only when asked, so typing in a grid or an editor costs nothing here.
 */
export type ProjectWork = {
    /**
     * The changes made in the grid of a table and not applied yet.
     */
    changes: { table: string; count: number }[];
    /**
     * The .sql files whose content could not be written: a conflict or an error the user has not settled.
     */
    unsavedFiles: string[];
    /**
     * The runs of the SQL editor still waiting for the database.
     */
    runs: string[];
    /**
     * Whether the agent is answering in the conversation shown.
     */
    answering: boolean;
};

export type ProjectWorkSource = {
    describe: () => Partial<ProjectWork>;
    /**
     * Writes what can still be written before the project is left, such as the text typed in the editor.
     */
    settle?: () => Promise<void>;
    /**
     * Stops reading what the server is asked to stop.
     */
    stop?: () => void;
};

const sources = new Set<ProjectWorkSource>();

/**
 * The runs are kept apart from the pages: a run goes on after its page is left, until the database answers.
 */
const runs = new Set<string>();

/**
 * @returns what removes the source, once the page holding the work is left
 */
export function registerProjectWork(source: ProjectWorkSource): () => void {
    sources.add(source);

    return () => {
        sources.delete(source);
    };
}

/**
 * @returns what forgets the run, once the database answered
 */
export function trackEditorRun(runId: string): () => void {
    runs.add(runId);

    return () => {
        runs.delete(runId);
    };
}

export function currentProjectWork(): ProjectWork {
    const work: ProjectWork = {
        changes: [],
        unsavedFiles: [],
        runs: [...runs],
        answering: false,
    };

    sources.forEach((source) => {
        const described = source.describe();

        work.changes.push(...(described.changes ?? []));
        work.unsavedFiles.push(...(described.unsavedFiles ?? []));
        work.answering ||= described.answering ?? false;
    });

    return work;
}

export function hasProjectWork(work: ProjectWork): boolean {
    return (
        work.changes.length > 0 ||
        work.unsavedFiles.length > 0 ||
        work.runs.length > 0 ||
        work.answering
    );
}

export async function settleProjectWork(): Promise<void> {
    await Promise.allSettled(
        [...sources].map((source) => source.settle?.()),
    );
}

export function stopProjectWork(): void {
    sources.forEach((source) => source.stop?.());
    runs.clear();
}
