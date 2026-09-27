import { FlaskConicalIcon } from 'lucide-react';
import Button from '@/components/form/button';
import type { ConnectionTestState } from '@/hooks/use-project';

type Props = {
    state: ConnectionTestState;
    processing: boolean;
    onTrigger: () => void;
};

const TestConnectionButton = ({ state, processing, onTrigger }: Props) => {
    return (
        <div>
            <Button
                variant="secondary"
                icon={FlaskConicalIcon}
                onClick={onTrigger}
                processing={processing}
                tooltipColor={
                    state.triggered
                        ? state.state
                            ? 'success'
                            : 'destructive'
                        : 'default'
                }
                stateAppearance={
                    state.triggered
                        ? state.state
                            ? 'success'
                            : 'destructive'
                        : undefined
                }
                tooltip={state.triggered ? state.message : undefined}
            >
                Tester la connexion
            </Button>
        </div>
    );
};


export default TestConnectionButton
