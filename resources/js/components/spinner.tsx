import { useLaravelReactI18n } from 'laravel-react-i18n';
import type React from 'react';
import { Spinner as UiSpinner } from '@/components/ui/spinner';

/**
 * The spinner of ui/spinner.tsx, announced in the language of the interface.
 */
export function Spinner(props: React.ComponentProps<typeof UiSpinner>) {
    const { t } = useLaravelReactI18n();

    return <UiSpinner aria-label={t('common.loading')} {...props} />;
}
