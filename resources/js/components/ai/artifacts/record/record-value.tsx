import { formatRelativeTime, parseJson, valueDisplay } from '@/components/ai/artifacts/record/format';
import { Badge } from '@/components/reui/badge';
import type { CellValue } from '@/types/agent';

/**
 * A value of the record, rendered after its kind: NULL and booleans as badges, JSON indented, dates with how long ago they were.
 */
export default function RecordValue({ value }: { value: CellValue }) {
    switch (valueDisplay(value)) {
        case 'null':
            return (
                <Badge variant="outline" className="border-dashed text-muted-foreground">
                    NULL
                </Badge>
            );
        case 'boolean':
            return <Badge variant="outline">{value ? 'true' : 'false'}</Badge>;
        case 'number':
            return <span className="text-sm tabular-nums">{String(value)}</span>;
        case 'temporal': {
            const relativeTime = formatRelativeTime(String(value));

            return (
                <span className="flex flex-wrap items-baseline gap-x-2">
                    <span className="text-sm tabular-nums">{String(value)}</span>
                    {relativeTime && <span className="text-xs text-muted-foreground">{relativeTime}</span>}
                </span>
            );
        }
        case 'json':
            return (
                <pre className="max-h-72 w-full overflow-auto rounded-lg border bg-muted/40 px-3 py-2.5 font-mono text-sm leading-relaxed">
                    {JSON.stringify(parseJson(String(value)), null, 2)}
                </pre>
            );
        default:
            return <span className="text-sm whitespace-pre-wrap wrap-break-word">{String(value)}</span>;
    }
}
