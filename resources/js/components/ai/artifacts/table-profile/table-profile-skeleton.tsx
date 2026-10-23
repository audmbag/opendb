import { LoaderCircleIcon } from 'lucide-react';
import type { Ref } from 'react';
import { cellClassName, ProfileColumnWidths } from '@/components/ai/artifacts/table-profile/column-row';
import { Card } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';

const statWidths: [number, number][] = [
    [70, 44],
    [90, 60],
    [100, 56],
    [76, 28],
];

const rowWidths = [110, 140, 90, 124];

/**
 * The card of the profile while the sample of the table is being analysed.
 */
export default function TableProfileSkeleton({ ref }: { ref?: Ref<HTMLDivElement> }) {
    return (
        <Card ref={ref} aria-busy="true" className="w-full max-w-228 gap-0 py-0">
            <div className="flex flex-wrap items-center justify-between gap-4 px-6 py-5">
                <div className="flex items-center gap-3">
                    <Skeleton className="size-9 rounded-lg" />
                    <div className="flex flex-col gap-1.5">
                        <Skeleton className="h-2.5 w-24 rounded-sm" />
                        <Skeleton className="h-3.5 w-16 rounded-sm" />
                    </div>
                </div>
                <span className="inline-flex items-center gap-2 text-xs text-muted-foreground">
                    <LoaderCircleIcon className="size-3.5 animate-spin" />
                    Analyse de l'échantillon en cours…
                </span>
            </div>

            <div className="grid grid-cols-[repeat(auto-fit,minmax(180px,1fr))] gap-px border-y bg-border">
                {statWidths.map(([label, value], index) => (
                    <div key={index} className="flex flex-col gap-2 bg-card px-6 py-4">
                        <Skeleton className="h-2.5 rounded-sm" style={{ width: label }} />
                        <Skeleton className="h-5 rounded-sm" style={{ width: value }} />
                    </div>
                ))}
            </div>

            <div className="overflow-x-auto">
                <table className="w-full min-w-225 table-fixed">
                    <ProfileColumnWidths />
                    <tbody>
                        {rowWidths.map((width, index) => (
                            <tr key={index} className={cn('[&>td]:h-14', index > 0 && 'border-t')}>
                                <td className={cn(cellClassName, 'first:pl-13')}>
                                    <div className="flex flex-col gap-1.5">
                                        <Skeleton className="h-2.5 rounded-sm" style={{ width }} />
                                        <Skeleton className="h-2 w-16 rounded-sm" />
                                    </div>
                                </td>
                                <td className={cellClassName}>
                                    <div className="flex flex-col gap-1.5">
                                        <Skeleton className="h-2.5 w-10 rounded-sm" />
                                        <Skeleton className="h-1.5 w-20 rounded-full" />
                                    </div>
                                </td>
                                <td className={cellClassName}>
                                    <Skeleton className="ml-auto h-2.5 w-10 rounded-sm" />
                                </td>
                                <td className={cellClassName}>
                                    <Skeleton className="h-2.5 w-4/5 rounded-sm" />
                                </td>
                                <td className={cellClassName}>
                                    <Skeleton className="h-2.5 w-[70%] rounded-sm" />
                                </td>
                                <td className={cellClassName}>
                                    <Skeleton className="ml-auto h-2.5 w-7 rounded-sm" />
                                </td>
                                <td className={cellClassName}>
                                    <Skeleton className="h-5 w-22 rounded-md" />
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </Card>
    );
}
