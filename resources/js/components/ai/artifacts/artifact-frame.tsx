import type { LucideIcon } from 'lucide-react';
import { TriangleAlertIcon } from 'lucide-react';
import type { ReactNode, Ref } from 'react';
import CardFrame from '@/components/card-frame';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';

type Props = {
    ref?: Ref<HTMLDivElement>;
    icon: LucideIcon;
    title: ReactNode;
    description?: ReactNode;
    actions?: ReactNode;
    badge?: ReactNode;
    data?: unknown;
    error?: string | null;
    processing?: boolean;
    skeletonClassName?: string;
    className?: string;
    children: ReactNode;
};

/**
 * The frame shared by the artifacts rendered under an answer: header, pulsing skeleton while loading, and error.
 */
export default function ArtifactFrame({
    ref,
    icon,
    title,
    description,
    actions,
    data,
    error,
    processing,
    badge,
    skeletonClassName = 'h-40',
    className,
    children,
}: Props) {
    return (
        <div ref={ref} className="w-full min-w-0">
            <CardFrame
                title={title as any}
                description={description}
                actions={actions}
                processing={processing}
                icon={icon}
                innerClassName={cn('p-0', className)}
                badge={badge}
            >
                {error && (
                    <div className="flex items-start gap-2 p-4 text-sm text-destructive">
                        <TriangleAlertIcon className="mt-0.5 size-4 shrink-0" />
                        <span className="wrap-break-word">{error}</span>
                    </div>
                )}

                {!error && data === null && (
                    <div className="p-4">
                        <Skeleton className={cn('w-full', skeletonClassName)} />
                    </div>
                )}

                {!error && data !== null && children}
            </CardFrame>
        </div>
    );
}
