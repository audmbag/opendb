import type { ChatStatus, DynamicToolUIPart, ToolUIPart, UIMessage } from 'ai';
import { isReasoningUIPart, isTextUIPart, isToolUIPart } from 'ai';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import {
    BrainIcon,
    LoaderIcon,
    ShieldQuestionMarkIcon,
    SparklesIcon,
    WrenchIcon,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import type { ReactNode } from 'react';
import { Shimmer } from '@/components/ai-elements/shimmer';
import { DotmSquare3 } from '@/components/ui/dotm-square-3';
import type { AgentToolNameType } from '@/types/agent';
import { getMetadata } from '@/types/agent';

type ProcessingActivity = {
    icon: ReactNode;
    label: string;
    /**
     * False when the agent is not working, but waiting for the user.
     */
    isWorking: boolean;
    /**
     * The model has not answered anything yet: the request is sent, or it thinks before its first word or tool call.
     */
    isAwaitingModel?: boolean;
};

/**
 * The time each label of a long wait for the model stays on screen.
 */
const AWAITING_STEP_MS = 6000;

const ACTIVITY_ICON_CLASS = 'size-4 animate-pulse text-muted-foreground';

/**
 * A tool waiting for its approval is not running yet, and a denied one will never run.
 */
const isToolRunning = (part: ToolUIPart | DynamicToolUIPart): boolean => {
    if (part.state === 'approval-responded') {
        return part.approval.approved;
    }

    return part.state === 'input-streaming' || part.state === 'input-available';
};

/**
 * What the agent is doing right now, read from the chat status and the parts of the latest message.
 */
export const resolveProcessingActivity = (
    status: ChatStatus,
    latestMessage: UIMessage | undefined,
    t: (key: string) => string,
): ProcessingActivity | null => {
    if (status === 'error') {
        return null;
    }

    const parts = latestMessage?.parts ?? [];
    const toolParts = parts.filter(isToolUIPart);

    if (toolParts.some((part) => part.state === 'approval-requested')) {
        return {
            icon: (
                <ShieldQuestionMarkIcon className="size-4 text-muted-foreground" />
            ),
            label: t('ai.activities.waiting_approval'),
            isWorking: false,
        };
    }

    if (status === 'ready') {
        return null;
    }

    const runningTool = toolParts.find(isToolRunning);

    if (runningTool) {
        const metadata = getMetadata(runningTool.type as AgentToolNameType);
        const ToolIcon = metadata?.icon ?? WrenchIcon;

        return {
            icon: <ToolIcon className={ACTIVITY_ICON_CLASS} />,
            label: `${t(metadata?.activityLabel ?? 'ai.activities.running_tool')}...`,
            isWorking: true,
        };
    }

    if (status === 'submitted' && latestMessage?.role !== 'assistant') {
        return {
            icon: (
                <LoaderIcon className="size-4 animate-spin text-muted-foreground" />
            ),
            label: t('ai.activities.sending'),
            isWorking: true,
            isAwaitingModel: true,
        };
    }

    const latestContentPart = parts.findLast(
        (part) =>
            isTextUIPart(part) || isReasoningUIPart(part) || isToolUIPart(part),
    );

    if (
        latestContentPart &&
        isTextUIPart(latestContentPart) &&
        latestContentPart.state === 'streaming'
    ) {
        return {
            icon: (
                <DotmSquare3
                    size={14}
                    dotSize={2}
                    aria-label={t('ai.activities.writing_short')}
                />
            ),
            label: t('ai.activities.writing'),
            isWorking: true,
        };
    }

    if (
        latestContentPart &&
        isToolUIPart(latestContentPart) &&
        (latestContentPart.state === 'output-available' ||
            latestContentPart.state === 'output-error')
    ) {
        return {
            icon: <SparklesIcon className={ACTIVITY_ICON_CLASS} />,
            label: t('ai.activities.analyzing_result'),
            isWorking: true,
        };
    }

    return {
        icon: <BrainIcon className={ACTIVITY_ICON_CLASS} />,
        label: t('ai.activities.thinking'),
        isWorking: true,
        isAwaitingModel: parts.length === 0,
    };
};

/**
 * The steps of a long wait shown one after the other, the last one staying until the model answers. Mounted for each
 * wait, it starts again from the first step.
 */
function AwaitingLabel({ labels }: { labels: string[] }) {
    const [step, setStep] = useState(0);

    useEffect(() => {
        const timer = setInterval(
            () =>
                setStep((current) => Math.min(current + 1, labels.length - 1)),
            AWAITING_STEP_MS,
        );

        return () => clearInterval(timer);
    }, [labels.length]);

    return (
        <Shimmer duration={1}>
            {labels[Math.min(step, labels.length - 1)]}
        </Shimmer>
    );
}

const ProcessingIndicator = ({
    status,
    latestMessage,
    awaitingLabels,
}: {
    status: ChatStatus;
    latestMessage: UIMessage | undefined;
    /**
     * What the agent does while the model has not answered yet, told step by step in place of the generic label.
     */
    awaitingLabels?: string[];
}) => {
    const { t } = useLaravelReactI18n();
    const activity = resolveProcessingActivity(status, latestMessage, t);

    if (!activity) {
        return null;
    }

    return (
        <div role="status" className="flex items-center gap-2">
            {activity.icon}
            {activity.isAwaitingModel && awaitingLabels?.length ? (
                <AwaitingLabel labels={awaitingLabels} />
            ) : activity.isWorking ? (
                <Shimmer duration={1}>{activity.label}</Shimmer>
            ) : (
                <span className="text-sm text-muted-foreground">
                    {activity.label}
                </span>
            )}
        </div>
    );
};

export default ProcessingIndicator;
