import type { ChatStatus, DynamicToolUIPart, ToolUIPart, UIMessage } from 'ai';
import { isReasoningUIPart, isTextUIPart, isToolUIPart } from 'ai';
import { BrainIcon, LoaderIcon, ShieldQuestionMarkIcon, SparklesIcon, WrenchIcon } from 'lucide-react';
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
};

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
): ProcessingActivity | null => {
    if (status === 'error') {
        return null;
    }

    const parts = latestMessage?.parts ?? [];
    const toolParts = parts.filter(isToolUIPart);

    if (toolParts.some((part) => part.state === 'approval-requested')) {
        return {
            icon: <ShieldQuestionMarkIcon className="size-4 text-muted-foreground" />,
            label: 'En attente de votre validation',
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
            label: `${metadata?.activityLabel ?? "Exécution d'un outil"}...`,
            isWorking: true,
        };
    }

    if (status === 'submitted' && latestMessage?.role !== 'assistant') {
        return {
            icon: <LoaderIcon className="size-4 animate-spin text-muted-foreground" />,
            label: 'Envoi du message...',
            isWorking: true,
        };
    }

    const latestContentPart = parts.findLast(
        (part) => isTextUIPart(part) || isReasoningUIPart(part) || isToolUIPart(part),
    );

    if (latestContentPart && isTextUIPart(latestContentPart) && latestContentPart.state === 'streaming') {
        return {
            icon: <DotmSquare3 size={14} dotSize={2} aria-label="Rédaction en cours" />,
            label: 'Rédaction de la réponse...',
            isWorking: true,
        };
    }

    if (
        latestContentPart &&
        isToolUIPart(latestContentPart) &&
        (latestContentPart.state === 'output-available' || latestContentPart.state === 'output-error')
    ) {
        return {
            icon: <SparklesIcon className={ACTIVITY_ICON_CLASS} />,
            label: 'Analyse du résultat...',
            isWorking: true,
        };
    }

    return {
        icon: <BrainIcon className={ACTIVITY_ICON_CLASS} />,
        label: 'Réflexion...',
        isWorking: true,
    };
};

const ProcessingIndicator = ({
    status,
    latestMessage,
}: {
    status: ChatStatus;
    latestMessage: UIMessage | undefined;
}) => {
    const activity = resolveProcessingActivity(status, latestMessage);

    if (!activity) {
        return null;
    }

    return (
        <div role="status" className="flex items-center gap-2">
            {activity.icon}
            {activity.isWorking ? (
                <Shimmer duration={1}>{activity.label}</Shimmer>
            ) : (
                <span className="text-sm text-muted-foreground">{activity.label}</span>
            )}
        </div>
    );
};

export default ProcessingIndicator;
