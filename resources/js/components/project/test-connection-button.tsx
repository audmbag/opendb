import { useLaravelReactI18n } from 'laravel-react-i18n';
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
    const { t } = useLaravelReactI18n();

    return (
        <div className="flex flex-wrap items-center gap-2">
            <Button
                variant="secondary"
                icon={FlaskConicalIcon}
                onClick={onTrigger}
                processing={processing}
            >
                {t('settings.mail.test_connection')}
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
                            ? t('projects.form.database_will_be_created')
                            : state.success
                              ? t('settings.mail.connection_succeeded')
                              : t('settings.mail.connection_failed')
                    }
                    message={
                        state.willCreateDatabase
                            ? t('projects.form.database_will_be_created_description')
                            : state.message
                    }
                />
            )}
        </div>
    );
};


export default TestConnectionButton
