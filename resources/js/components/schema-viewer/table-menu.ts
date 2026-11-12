import { createContext } from 'react';
import type { ReactNode } from 'react';

/**
 * The items of the context menu of a table of the diagram. Without them, as in the artifacts of the conversation,
 * a right click on a table does nothing.
 */
export type SchemaTableMenu = (table: string) => ReactNode;

export const SchemaTableMenuContext = createContext<SchemaTableMenu | null>(
    null,
);
