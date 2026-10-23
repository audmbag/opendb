import { ArrowDownLeftIcon, ArrowUpRightIcon, ChevronRightIcon } from 'lucide-react';
import { describeIncoming, describeKey } from '@/components/ai/artifacts/record/format';
import type { RecordLocation } from '@/components/ai/artifacts/record/format';
import { formatInteger } from '@/components/ai/artifacts/table-profile/format';
import CardFrame from '@/components/card-frame';
import { Badge } from '@/components/reui/badge';
import { cn } from '@/lib/utils';
import type { InspectedRecord } from '@/types/agent';

type Props = {
    record: InspectedRecord;
    onOpen: (location: RecordLocation) => void;
};

function Count({ value }: { value: number }) {
    return (
        <Badge variant="outline" className="tabular-nums text-muted-foreground">
            {formatInteger(value)}
        </Badge>
    );
}

/**
 * The records the foreign keys of the record point to, which open in the card, and the number of records pointing to it.
 */
export default function RecordRelations({ record, onOpen }: Props) {
    const incoming = [...record.incoming].sort((first, second) => second.count - first.count);
    const incomingTotal = incoming.reduce((sum, relation) => sum + relation.count, 0);

    return (
        <div className="flex flex-col gap-2 p-2">
            <CardFrame title="Références sortantes" icon={ArrowUpRightIcon} badge={<Count value={record.outgoing.length} />}>
                {record.outgoing.length === 0 ? (
                    <p className="text-sm text-muted-foreground">Cet enregistrement ne référence aucune autre table.</p>
                ) : (
                    <div className="flex flex-col gap-2">
                        {record.outgoing.map((relation) => (
                            <button
                                key={relation.columns.join('-')}
                                type="button"
                                className="flex w-full items-center gap-3 rounded-lg border px-3 py-2.5 text-left outline-none transition-colors hover:border-foreground/20 hover:bg-muted/40 focus-visible:ring-3 focus-visible:ring-ring/50"
                                onClick={() => onOpen({ table: relation.table, key: relation.key })}
                            >
                                <div className="flex min-w-0 flex-1 flex-col gap-0.5">
                                    <span className="truncate text-sm font-medium">{relation.table}</span>
                                    <span className="truncate text-xs text-muted-foreground">
                                        {relation.columns.join(', ')} → {describeKey(relation.key)}
                                    </span>
                                </div>
                                <ChevronRightIcon className="size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
                            </button>
                        ))}
                    </div>
                )}
            </CardFrame>

            <CardFrame title="Références entrantes" icon={ArrowDownLeftIcon} badge={<Count value={incomingTotal} />}>
                <p className="text-sm text-muted-foreground">{describeIncoming(incoming)}</p>

                {incoming.length > 0 && (
                    <ul className="mt-2">
                        {incoming.map((relation) => (
                            <li
                                key={`${relation.table}-${relation.columns.join('-')}`}
                                className="flex items-center justify-between gap-3 border-t py-2 text-sm last:pb-0"
                            >
                                <span className="min-w-0 truncate">
                                    <span className={cn(relation.count === 0 && 'text-muted-foreground')}>{relation.table}</span>
                                    <span className="text-muted-foreground">.{relation.columns.join(', ')}</span>
                                </span>
                                <Badge
                                    variant={relation.count > 0 ? 'secondary' : 'outline'}
                                    radius="full"
                                    className={cn('min-w-7 tabular-nums', relation.count === 0 && 'text-muted-foreground')}
                                >
                                    {formatInteger(relation.count)}
                                </Badge>
                            </li>
                        ))}
                    </ul>
                )}
            </CardFrame>
        </div>
    );
}
