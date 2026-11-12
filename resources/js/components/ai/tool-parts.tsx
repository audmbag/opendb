import type { ToolUIPart } from 'ai';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { CheckCircleIcon, CircleIcon, ClockIcon, XCircleIcon } from 'lucide-react';
import type { ComponentProps, ReactNode } from 'react';
import { isValidElement } from 'react';
import { CodeBlock } from '@/components/ai-elements/code-block';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

/**
 * The parts of a tool call of ai-elements/tool.tsx whose text is written in English, copied to speak the language of the
 * interface: the status badge, and the titles of the parameters and of the result.
 */
const STATUS_ICONS: Record<ToolUIPart['state'], ReactNode> = {
    'approval-requested': <ClockIcon className="size-4 text-yellow-600" />,
    'approval-responded': <CheckCircleIcon className="size-4 text-blue-600" />,
    'input-available': <ClockIcon className="size-4 animate-pulse" />,
    'input-streaming': <CircleIcon className="size-4" />,
    'output-available': <CheckCircleIcon className="size-4 text-green-600" />,
    'output-denied': <XCircleIcon className="size-4 text-orange-600" />,
    'output-error': <XCircleIcon className="size-4 text-red-600" />,
};

export function ToolStatusBadge({ status }: { status: ToolUIPart['state'] }) {
    const { t } = useLaravelReactI18n();

    return (
        <Badge className="gap-1.5 rounded-full text-xs" variant="secondary">
            {STATUS_ICONS[status]}
            {t(`ai.tools.statuses.${status}`)}
        </Badge>
    );
}

export function ToolInput({ className, input, ...props }: ComponentProps<'div'> & { input: ToolUIPart['input'] }) {
    const { t } = useLaravelReactI18n();

    return (
        <div className={cn('space-y-2 overflow-hidden', className)} {...props}>
            <h4 className="text-xs font-medium tracking-wide text-muted-foreground uppercase">{t('ai.tools.parameters')}</h4>
            <div className="rounded-md bg-muted/50">
                <CodeBlock code={JSON.stringify(input, null, 2)} language="json" />
            </div>
        </div>
    );
}

export function ToolOutput({
    className,
    output,
    errorText,
    ...props
}: ComponentProps<'div'> & { output: ToolUIPart['output']; errorText: ToolUIPart['errorText'] }) {
    const { t } = useLaravelReactI18n();

    if (!(output || errorText)) {
        return null;
    }

    let content = <div>{output as ReactNode}</div>;

    if (typeof output === 'object' && !isValidElement(output)) {
        content = <CodeBlock code={JSON.stringify(output, null, 2)} language="json" />;
    } else if (typeof output === 'string') {
        content = <CodeBlock code={output} language="json" />;
    }

    return (
        <div className={cn('space-y-2', className)} {...props}>
            <h4 className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                {errorText ? t('ai.tools.error') : t('ai.tools.result')}
            </h4>
            <div
                className={cn(
                    'overflow-x-auto rounded-md text-xs [&_table]:w-full',
                    errorText ? 'bg-destructive/10 text-destructive' : 'bg-muted/50 text-foreground',
                )}
            >
                {errorText && <div>{errorText}</div>}
                {content}
            </div>
        </div>
    );
}
