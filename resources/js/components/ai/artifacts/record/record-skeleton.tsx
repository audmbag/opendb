import { Skeleton } from '@/components/ui/skeleton';

const rowWidths: [number, number][] = [
    [64, 48],
    [96, 120],
    [110, 72],
    [80, 180],
    [120, 96],
    [72, 140],
];

/**
 * The body of the card while the record is being read, shaped like its columns and relations.
 */
export default function RecordSkeleton() {
    return (
        <div aria-busy="true" className="flex flex-wrap gap-px bg-border">
            <div className="min-w-0 flex-[999_1_30rem] bg-card">
                <div className="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                    <span className="text-sm text-muted-foreground">Lecture de l'enregistrement…</span>
                    <Skeleton className="h-8 w-56 rounded-md" />
                </div>
                {rowWidths.map(([name, value], index) => (
                    <div key={index} className="grid grid-cols-[minmax(8rem,12rem)_minmax(0,1fr)] items-center gap-x-5 border-t px-4 py-4">
                        <Skeleton className="h-3 rounded-sm" style={{ width: name }} />
                        <Skeleton className="h-3 rounded-sm" style={{ width: value }} />
                    </div>
                ))}
            </div>

            <div className="flex min-w-0 flex-[1_1_18rem] flex-col gap-2 bg-card p-2">
                {[2, 2].map((items, index) => (
                    <div key={index} className="flex flex-col gap-2 rounded-xl border bg-muted/50 p-3">
                        <Skeleton className="h-3.5 w-36 rounded-sm" />
                        {Array.from({ length: items }, (_, item) => (
                            <Skeleton key={item} className="h-14 w-full rounded-lg" />
                        ))}
                    </div>
                ))}
            </div>
        </div>
    );
}
