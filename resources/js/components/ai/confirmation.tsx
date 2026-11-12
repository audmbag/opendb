import type { ToolUIPart } from 'ai';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { ShieldAlertIcon } from 'lucide-react';
import WriteImpactBadge from '@/components/ai/artifacts/write-impact-badge';
import {
    Confirmation as AIElementConfirmation,
    ConfirmationAction,
    ConfirmationActions,
    ConfirmationRequest,
    ConfirmationTitle,
} from '@/components/ai-elements/confirmation';
import CardFrame from '@/components/card-frame';
import { CodeBlock } from '@/components/code-block';
import { Badge } from '@/components/reui/badge';
import { translateMessage } from '@/lib/i18n';
import { uppercaseSqlKeywords } from '@/lib/sql';

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
    const { t } = useLaravelReactI18n();

    if (!part) {
        return null;
    }

    const toolName = part.type.split('-').slice(1).join('-');
    const input = (part.input ?? {}) as ApprovalInput;
    const query =
        typeof input.query === 'string'
            ? uppercaseSqlKeywords(input.query.trim())
            : null;
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
                    {t('ai.approvals.required')}
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
                            {translateMessage(t, approvalReason)}
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
                        {t('ai.approvals.reject')}
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
                        {t('ai.approvals.approve')}
                    </ConfirmationAction>
                </ConfirmationActions>
            </AIElementConfirmation>
        </CardFrame>
    );
};

export default Confirmation;
