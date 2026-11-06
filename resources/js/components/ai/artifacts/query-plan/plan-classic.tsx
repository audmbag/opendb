import { formatCell } from '@/components/ai/artifacts/format';
import { cn } from '@/lib/utils';
import type { ClassicPlan } from '@/types/agent';

/**
 * The plan as the engine usually shows it: the EXPLAIN table of MySQL, MariaDB and SQLite, or the text plan of PostgreSQL.
 */
export default function PlanClassic({ classic }: { classic: ClassicPlan }) {
    if (classic.text !== null) {
        return (
            <div className="border-t px-6 py-4">
                <pre className="overflow-auto rounded-lg border bg-muted/40 px-3.5 py-3 font-mono text-xs leading-[1.6]">
                    {classic.text}
                </pre>
            </div>
        );
    }

    const columns = classic.columns ?? [];

    return (
        <div className="overflow-x-auto border-t">
            <table className="w-full min-w-205 border-collapse text-xs">
                <thead>
                    <tr>
                        {columns.map((column) => (
                            <th
                                key={column}
                                scope="col"
                                className="px-3 py-2.5 text-left font-medium whitespace-nowrap text-muted-foreground first:pl-6 last:pr-6"
                            >
                                {column}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {(classic.rows ?? []).map((row, rowIndex) => (
                        <tr key={rowIndex} className="border-t">
                            {row.map((value, index) => (
                                <td
                                    key={index}
                                    className={cn(
                                        'px-3 py-3 font-mono whitespace-nowrap first:pl-6 last:pr-6',
                                        value === null &&
                                            'text-muted-foreground',
                                        columns[index] === 'type' &&
                                            value === 'ALL' &&
                                            'font-medium text-red-600 dark:text-red-400',
                                    )}
                                >
                                    {formatCell(value)}
                                </td>
                            ))}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
