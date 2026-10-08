import { FlaskConicalIcon } from 'lucide-react';
import Alert from '@/components/alert';
import Button from '@/components/form/button';
import type { ConnectionTestState } from '@/hooks/use-project';

type Props = {
    state: ConnectionTestState;
    processing: boolean;
    onTrigger: () => void;
};

const TestConnectionButton = ({ state, processing, onTrigger }: Props) => {
    return (
        <div className="flex flex-wrap items-center gap-2">
            <Button
                variant="secondary"
                icon={FlaskConicalIcon}
                onClick={onTrigger}
                processing={processing}
            >
                Tester la connexion
            </Button>
            {state.triggered && (
                <Alert
                    variant={
                        state.willCreateDatabase
                            ? 'success'
                            : state.success
                              ? 'success'
                              : 'destructive'
                    }
                    title={
                        state.willCreateDatabase
                            ? 'Une nouvelle base de données sera créée'
                            : state.success
                              ? 'Connexion réussie'
                              : 'Connexion échouée'
                    }
                    message={
                        state.willCreateDatabase
                            ? "La base de données que vous avez sélectionnée n'existe pas. Une nouvelle base de données sera créée pour vous."
                            : state.message
                    }
                />
            )}
        </div>
    );
};


export default TestConnectionButton
