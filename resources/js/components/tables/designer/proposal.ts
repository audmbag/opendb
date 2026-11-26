import type {
    DesignerColumn,
    DesignerForeignKey,
    DesignerIndex,
} from './design';
import { definitionDiffers } from './design';

/**
 * What the form of the designer holds.
 */
export type DesignerState = {
    name: string;
    columns: DesignerColumn[];
    primaryKey: string[];
    indexes: DesignerIndex[];
    foreignKeys: DesignerForeignKey[];
};

/**
 * A table proposed by the assistant, shown in the form until the user accepts or rejects it, with the form as it was
 * before, put back when the user rejects it.
 */
export type DesignProposal = {
    explanation: string;
    before: DesignerState;
};

export type ProposalMark = 'added' | 'changed';

/**
 * A request of the user to the assistant of the designer, and what came of it:
 * - pending: the assistant works on it;
 * - proposed: its table fills the form, until the user accepts or rejects it;
 * - refined: a later request changed its table before the user decided;
 * - accepted, rejected: what the user decided;
 * - declined: the assistant proposed no table, and says why.
 */
export type AssistantTurn = {
    id: string;
    prompt: string;
    answer: string | null;
    /**
     * What its table changed in the form as it was when asked.
     */
    changes: ProposalChanges | null;
    outcome:
        | 'pending'
        | 'proposed'
        | 'refined'
        | 'accepted'
        | 'rejected'
        | 'declined';
};

/**
 * What the proposal changes in the form: the rows it adds or changes, by local id, and the columns it takes away.
 */
export type ProposalChanges = {
    renamed: boolean;
    columns: Record<string, ProposalMark>;
    removedColumns: string[];
    indexes: Set<string>;
    foreignKeys: Set<string>;
};

const indexKey = (index: DesignerIndex): string =>
    `${index.unique}:${index.columns.join(',')}`;

const foreignKeyKey = (foreignKey: DesignerForeignKey): string =>
    [
        foreignKey.columns.join(','),
        foreignKey.referencesTable,
        foreignKey.referencesColumns.join(','),
        foreignKey.onDelete ?? '',
        foreignKey.onUpdate ?? '',
    ].join(':');

export function proposalChanges(
    before: DesignerState,
    after: DesignerState,
): ProposalChanges {
    const columnsBefore = new Map(
        before.columns.map((column) => [column.name, column]),
    );
    const namesAfter = new Set(after.columns.map((column) => column.name));
    const indexesBefore = new Set(before.indexes.map(indexKey));
    const foreignKeysBefore = new Set(before.foreignKeys.map(foreignKeyKey));
    const columns: Record<string, ProposalMark> = {};

    for (const column of after.columns) {
        const previous = columnsBefore.get(column.name);

        if (!previous) {
            columns[column.id] = 'added';
        } else if (
            definitionDiffers(column, previous) ||
            before.primaryKey.includes(column.name) !==
                after.primaryKey.includes(column.name)
        ) {
            columns[column.id] = 'changed';
        }
    }

    return {
        renamed: before.name !== after.name,
        columns,
        removedColumns: before.columns
            .map((column) => column.name)
            .filter((name) => name !== '' && !namesAfter.has(name)),
        indexes: new Set(
            after.indexes
                .filter((index) => !indexesBefore.has(indexKey(index)))
                .map((index) => index.id),
        ),
        foreignKeys: new Set(
            after.foreignKeys
                .filter(
                    (foreignKey) =>
                        !foreignKeysBefore.has(foreignKeyKey(foreignKey)),
                )
                .map((foreignKey) => foreignKey.id),
        ),
    };
}
