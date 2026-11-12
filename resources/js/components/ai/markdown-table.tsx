import type { Components, ExtraProps } from 'streamdown';
import { cn } from '@/lib/utils';

/**
 * The attributes of an element without the markdown node Streamdown gives along, which the DOM does not take.
 */
function domProps<T extends ExtraProps>(props: T): Omit<T, 'node'> {
    const attributes = { ...props };

    delete attributes.node;

    return attributes;
}

/**
 * The tables of the messages drawn plainly: no frame nor copy, download and fullscreen buttons around them, and
 * tighter cells than those of Streamdown, so that a table takes no more room than its rows.
 */
export const markdownTableComponents: Components = {
    table: ({ className, ...props }) => (
        <div className="my-3 w-fit max-w-full overflow-x-auto rounded-md border">
            <table
                className={cn('divide-y divide-border text-sm', className)}
                data-streamdown="table"
                {...domProps(props)}
            />
        </div>
    ),
    th: ({ className, ...props }) => (
        <th
            className={cn(
                'px-3 py-1.5 text-left font-medium whitespace-nowrap',
                className,
            )}
            data-streamdown="table-header-cell"
            {...domProps(props)}
        />
    ),
    td: ({ className, ...props }) => (
        <td
            className={cn('px-3 py-1.5', className)}
            data-streamdown="table-cell"
            {...domProps(props)}
        />
    ),
};
