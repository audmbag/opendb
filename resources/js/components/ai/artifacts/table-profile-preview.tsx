import { useLaravelReactI18n } from 'laravel-react-i18n';
import { CircleAlertIcon } from 'lucide-react';
import { useConversationId } from '@/components/ai/artifacts/format';
import TableProfile from '@/components/ai/artifacts/table-profile/table-profile';
import TableProfileSkeleton from '@/components/ai/artifacts/table-profile/table-profile-skeleton';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Card } from '@/components/ui/card';
import { useToolArtifact } from '@/hooks/use-tool-artifact';
import type { ProfileTableToolUIPart, TableProfile as TableProfileData } from '@/types/agent';
import { profile } from '@/wayfinder/actions/App/Http/Controllers/Chat/ToolArtifactController';

type Props = {
    part: ProfileTableToolUIPart;
    enabled: boolean;
};

export default function TableProfilePreview({ part, enabled }: Props) {
    const { t } = useLaravelReactI18n();
    const conversationId = useConversationId();

    const { ref, data, error } = useToolArtifact<TableProfileData>(
        profile.url({ conversation: conversationId, toolCallId: part.toolCallId }),
        enabled,
    );

    if (error) {
        return (
            <Card ref={ref} className="w-full max-w-228 px-6 py-5">
                <Alert variant="destructive" className="border-destructive/30">
                    <CircleAlertIcon />
                    <AlertTitle>
                        {t('ai.profile.failed')} <span className="font-mono">{part.output.table}</span>
                    </AlertTitle>
                    <AlertDescription className="text-[13px] text-muted-foreground!">{error}</AlertDescription>
                </Alert>
            </Card>
        );
    }

    if (data === null) {
        return <TableProfileSkeleton ref={ref} />;
    }

    return <TableProfile ref={ref} profile={data} />;
}
