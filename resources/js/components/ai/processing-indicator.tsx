import type { ChatStatus } from 'ai';
import { Loader, Loader2 } from 'lucide-react';
import { Shimmer } from '@/components/ai-elements/shimmer';

const ProcessingIndicator = ({ status }: { status: ChatStatus }) => {
    return (
        <>
            {status === 'submitted' && (
                <div className="flex items-center gap-1">
                    <Loader2 className="size-4 animate-spin" />
                    <Shimmer duration={1}>Connexion...</Shimmer>
                </div>
            )}
            {status === 'streaming' && (
                <div className="flex items-center gap-1">
                    <Loader className="size-4 animate-spin" />
                    <Shimmer duration={1}>Réflexion...</Shimmer>
                </div>
            )}
        </>
    );
};

export default ProcessingIndicator;
