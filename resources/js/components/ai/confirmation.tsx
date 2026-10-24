import type { ToolUIPart } from 'ai';
import { ShieldAlertIcon } from 'lucide-react';
import WriteImpactBadge from '@/components/ai/artifacts/write-impact-badge';
import { CodeBlock } from '@/components/ai-elements/code-block';
import {
    Confirmation as AIElementConfirmation,
    ConfirmationAction,
    ConfirmationActions,
    ConfirmationRequest,
    ConfirmationTitle,
} from '@/components/ai-elements/confirmation';
import CardFrame from '@/components/card-frame';
import { Badge } from '@/components/reui/badge';

type ApprovalInput = {
    query?: string;
    bindings?: Record<string, unknown>;
    [key: string]: unknown;
};

const Confirmation = ({
    part,
    approvalReason,
    onApprovalResponded,
}: {
    part: ToolUIPart | undefined;
    approvalReason: string | undefined;
    onApprovalResponded?: ({ id, approved }: { id: string; approved: boolean }) => void;
}) => {

    if (!part) {
        return null;
    }

    const toolName = part.type.split('-').slice(1).join('-');
    const input = (part.input ?? {}) as ApprovalInput;
    const query = typeof input.query === 'string' ? input.query.trim() : null;
    const bindings = input.bindings;

    return (
        <CardFrame className="rounded-b-none">
            <AIElementConfirmation
                approval={part.approval}
                state={part.state}
                className="gap-3 rounded-b-none border-0 border-b-0 p-0"
            >
                <div className="flex items-center gap-2 text-sm font-medium">
                    <ShieldAlertIcon className="size-4 text-amber-500" />
                    Approbation requise
                    <Badge variant="secondary" className="font-mono">
                        {toolName}
                    </Badge>
                    {toolName === 'WriteQuery' && (
                        <WriteImpactBadge toolCallId={part.toolCallId} />
                    )}
                </div>

                {query ? (
                    <CardFrame
                        innerPadding={false}
                        className="rounded-sm"
                        innerClassName="rounded-sm"
                    >
                        <CodeBlock
                            code={query}
                            language="sql"
                            className="border-0 [&_pre]:wrap-break-word [&_pre]:whitespace-pre-wrap"
                        />
                    </CardFrame>
                ) : (
                    <CardFrame
                        innerPadding={false}
                        className="rounded-sm"
                        innerClassName="rounded-sm"
                    >
                        <CodeBlock
                            code={JSON.stringify(input, null, 2)}
                            language="json"
                            className="border-0 [&_pre]:wrap-break-word [&_pre]:whitespace-pre-wrap"
                        />
                    </CardFrame>
                )}

                {bindings && (
                    <CardFrame
                        innerPadding={false}
                        className="rounded-sm"
                        innerClassName="rounded-sm"
                    >
                        <CodeBlock
                            code={JSON.stringify(bindings, null, 2)}
                            language="json"
                            className="border-0 [&_pre]:wrap-break-word [&_pre]:whitespace-pre-wrap"
                        />
                    </CardFrame>
                )}

                {approvalReason && (
                    <ConfirmationTitle className="block text-sm text-muted-foreground">
                        <ConfirmationRequest>
                            {approvalReason}
                        </ConfirmationRequest>
                    </ConfirmationTitle>
                )}

                <ConfirmationActions>
                    <ConfirmationAction
                        onClick={() =>
                            onApprovalResponded?.({
                                id: part.approval!.id,
                                approved: false,
                            })
                        }
                        variant="outline"
                    >
                        Rejeter
                    </ConfirmationAction>
                    <ConfirmationAction
                        onClick={() =>
                            onApprovalResponded?.({
                                id: part.approval!.id,
                                approved: true,
                            })
                        }
                        variant="default"
                    >
                        Approuver
                    </ConfirmationAction>
                </ConfirmationActions>
            </AIElementConfirmation>
        </CardFrame>
    );
};

export default Confirmation;
