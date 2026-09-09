import type { ToolUIPart } from 'ai';
import { ChevronRightIcon } from 'lucide-react';
import { WrenchIcon } from 'lucide-react';
import type { ToolHeaderProps} from '@/components/ai-elements/tool';
import {
    getStatusBadge,
    Tool as AIElementTool,
    ToolContent,
    ToolInput,
    ToolOutput,
} from '@/components/ai-elements/tool';
import { CollapsibleTrigger } from '@/components/ui/collapsible';
import { cn } from '@/lib/utils';
import type { AgentToolNameType, ToolMetadata } from '@/types/agent';
import { getMetadata } from '@/types/agent';




export const ToolHeader = ({
    className,
    title,
    type,
    state,
    toolName,
    toolMetadata,
    ...props
}: ToolHeaderProps & { toolMetadata?: ToolMetadata }) => {
    const derivedName =
        type === 'dynamic-tool' ? toolName : type.split('-').slice(1).join('-');

    return (
        <CollapsibleTrigger
            className={cn(
                'flex w-full items-center justify-between gap-4 p-3',
                className,
            )}
            {...props}
        >
            <div className="flex items-center gap-2">
                {toolMetadata ? (
                    <toolMetadata.icon
                        className={cn('size-4 text-muted-foreground')}
                    />
                ) : (
                    <WrenchIcon className="size-4 text-muted-foreground" />
                )}
                <span className="text-sm font-medium">
                    {title ?? derivedName}
                </span>
                {getStatusBadge(state)}
                <ChevronRightIcon className="size-3 text-muted-foreground transition-transform group-data-[state=open]:rotate-90" />
            </div>
        </CollapsibleTrigger>
    );
};

const Tool = ({ part }: { part: ToolUIPart }) => {
    return (
        <AIElementTool key={part.toolCallId} className="m-0 border-0">
            <ToolHeader
                type={part.type}
                state={part.state}
                className="p-0"
                toolMetadata={getMetadata(part.type as AgentToolNameType)}
            />
            <ToolContent>
                <ToolInput input={part.input} />
                <ToolOutput output={part.output} errorText={part.errorText} />
            </ToolContent>
        </AIElementTool>
    );
};

export default Tool;
