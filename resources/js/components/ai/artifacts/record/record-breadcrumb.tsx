import { useLaravelReactI18n } from 'laravel-react-i18n';
import { ArrowLeftIcon } from 'lucide-react';
import { Fragment } from 'react';
import { describeLocation } from '@/components/ai/artifacts/record/format';
import type { RecordLocation } from '@/components/ai/artifacts/record/format';
import { Breadcrumb, BreadcrumbItem, BreadcrumbLink, BreadcrumbList, BreadcrumbPage, BreadcrumbSeparator } from '@/components/ui/breadcrumb';
import { Button } from '@/components/ui/button';

type Props = {
    stack: RecordLocation[];
    onNavigate: (depth: number) => void;
};

/**
 * The records opened from the one of the tool call, to go back to any of them.
 */
export default function RecordBreadcrumb({ stack, onNavigate }: Props) {
    const { t } = useLaravelReactI18n();

    return (
        <div className="flex flex-wrap items-center gap-2 border-b px-2 py-2">
            <Button variant="ghost" size="sm" onClick={() => onNavigate(stack.length - 2)}>
                <ArrowLeftIcon />
                {t('common.back')}
            </Button>

            <span className="h-4 w-px bg-border" aria-hidden="true" />

            <Breadcrumb aria-label={t('ai.record.navigation')} className="min-w-0 px-1">
                <BreadcrumbList className="gap-1.5 sm:gap-1.5">
                    {stack.map((location, depth) => (
                        <Fragment key={depth}>
                            {depth > 0 && <BreadcrumbSeparator />}
                            <BreadcrumbItem>
                                {depth === stack.length - 1 ? (
                                    <BreadcrumbPage className="font-medium">{describeLocation(location)}</BreadcrumbPage>
                                ) : (
                                    <BreadcrumbLink asChild>
                                        <button type="button" className="rounded-sm outline-none focus-visible:ring-3 focus-visible:ring-ring/50" onClick={() => onNavigate(depth)}>
                                            {describeLocation(location)}
                                        </button>
                                    </BreadcrumbLink>
                                )}
                            </BreadcrumbItem>
                        </Fragment>
                    ))}
                </BreadcrumbList>
            </Breadcrumb>
        </div>
    );
}
