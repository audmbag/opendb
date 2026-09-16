import type { ChatStatus } from 'ai';
import { Loader } from 'lucide-react';
import { Shimmer } from '@/components/ai-elements/shimmer';
import { DotmSquare3 } from '@/components/ui/dotm-square-3';

const ProcessingIndicator = ({ status }: { status: ChatStatus }) => {
    return (
        <>
            {status === 'submitted' && (
                <div className="flex items-center gap-1">
                    <Loader className="size-4 animate-spin" />
                    <Shimmer duration={1}>Réflexion...</Shimmer>
                </div>
            )}
            {status === 'streaming' && (
                <div className="flex items-center gap-1">
                    <DotmSquare3 size={18} dotSize={3} aria-label="Saving" />
                    <Shimmer duration={1}>Génération...</Shimmer>
                </div>
            )}
        </>
    );
};

export default ProcessingIndicator;
