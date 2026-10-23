import { CheckIcon, CircleAlertIcon, CopyIcon, FileTextIcon } from 'lucide-react';
import { useRef, useState } from 'react';
import { useConversationId } from '@/components/ai/artifacts/format';
import { describeKey, describeLocation } from '@/components/ai/artifacts/record/format';
import type { RecordLocation } from '@/components/ai/artifacts/record/format';
import RecordBreadcrumb from '@/components/ai/artifacts/record/record-breadcrumb';
import RecordFields from '@/components/ai/artifacts/record/record-fields';
import RecordRelations from '@/components/ai/artifacts/record/record-relations';
import RecordSkeleton from '@/components/ai/artifacts/record/record-skeleton';
import CardFrame from '@/components/card-frame';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { useToolArtifact } from '@/hooks/use-tool-artifact';
import type { InspectedRecord, RecordKey, RecordToolUIPart } from '@/types/agent';
import { record } from '@/wayfinder/actions/App/Http/Controllers/Chat/ToolArtifactController';

type Props = {
    part: RecordToolUIPart;
    enabled: boolean;
};

const COPIED_DURATION = 1600;

export default function RecordPreview({ part, enabled }: Props) {
    const conversationId = useConversationId();
    const cardRef = useRef<HTMLDivElement>(null);

    /**
     * The record of the tool call, then the related records the user opened from it: the last one is displayed.
     */
    const [stack, setStack] = useState<RecordLocation[]>([{ table: part.output.table, key: part.output.key as RecordKey }]);
    const [hideNull, setHideNull] = useState(false);
    const [copied, setCopied] = useState(false);

    const current = stack[stack.length - 1];

    const { ref, data, error, processing } = useToolArtifact<InspectedRecord>(
        record.url(
            { conversation: conversationId, toolCallId: part.toolCallId },
            stack.length > 1 ? { query: { table: current.table, key: current.key } } : undefined,
        ),
        enabled,
    );

    const navigate = (nextStack: RecordLocation[]) => {
        setStack(nextStack);
        cardRef.current?.focus({ preventScroll: true });
    };

    const copyAsJson = async () => {
        if (!data) {
            return;
        }

        await navigator.clipboard.writeText(JSON.stringify(data.values, null, 2));
        setCopied(true);
        setTimeout(() => setCopied(false), COPIED_DURATION);
    };

    const open = (location: RecordLocation) => navigate([...stack, location]);

    return (
        <div ref={ref} className="w-full max-w-228 min-w-0">
            <CardFrame
                ref={cardRef}
                tabIndex={-1}
                className="outline-none"
                title={current.table}
                description={describeKey(current.key)}
                icon={FileTextIcon}
                processing={processing}
                innerPadding={false}
                actions={
                    data &&
                    !processing &&
                    !error && (
                        <Button variant="outline" size="sm" onClick={copyAsJson}>
                            {copied ? <CheckIcon /> : <CopyIcon />}
                            {copied ? 'Copié' : 'Copier en JSON'}
                        </Button>
                    )
                }
            >
                {stack.length > 1 && <RecordBreadcrumb stack={stack} onNavigate={(depth) => navigate(stack.slice(0, depth + 1))} />}

                {processing ? (
                    <RecordSkeleton />
                ) : error || !data ? (
                    <div className="p-2">
                        <Alert variant="destructive" className="border-destructive/30">
                            <CircleAlertIcon />
                            <AlertTitle>Impossible d'afficher l'enregistrement</AlertTitle>
                            <AlertDescription className="text-muted-foreground!">{error}</AlertDescription>
                        </Alert>
                    </div>
                ) : (
                    <div className="flex flex-wrap gap-px bg-border">
                        <section aria-label="Colonnes" className="@container min-w-0 flex-[999_1_30rem] bg-card">
                            <RecordFields
                                key={describeLocation(current)}
                                record={data}
                                hideNull={hideNull}
                                onHideNullChange={setHideNull}
                                onOpen={open}
                            />
                        </section>
                        <section aria-label="Relations" className="min-w-0 flex-[1_1_18rem] bg-card">
                            <RecordRelations record={data} onOpen={open} />
                        </section>
                    </div>
                )}
            </CardFrame>
        </div>
    );
}
