import type { ToolUIPart } from 'ai';
import {
    Confirmation as AIElementConfirmation,
    ConfirmationAction,
    ConfirmationActions,
    ConfirmationRequest,
    ConfirmationTitle,
} from '@/components/ai-elements/confirmation';


const Confirmation = ({
    part,
    approvalReason,
    onApprovalResponded,
}: {
    part: ToolUIPart | undefined;
    approvalReason: string | undefined;
    onApprovalResponded?: ({ id, approved }: { id: string; approved: boolean }) => void;
}) => {

    if (!part) {
        return null;
    }

    return (
        <AIElementConfirmation approval={part.approval} state={part.state}>
            <ConfirmationTitle>
                <ConfirmationRequest>{approvalReason}</ConfirmationRequest>
            </ConfirmationTitle>
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
                    Rejeter
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
                    Approuver
                </ConfirmationAction>
            </ConfirmationActions>
        </AIElementConfirmation>
    );
};

export default Confirmation;
