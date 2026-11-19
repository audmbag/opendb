import { usePage } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { useEffect } from 'react';
import { toast } from 'sonner';
import { useSettingsDialog } from '@/hooks/use-settings-modal';

const TOAST_ID = 'default-password';

/**
 * Right after signing in with the password every installation starts with, suggests changing it from the account
 * settings. The suggestion stays until the user answers it, and they may leave it for later.
 */
export default function DefaultPasswordNotice() {
    const { t } = useLaravelReactI18n();
    const { flash } = usePage();
    const { open: openSettings } = useSettingsDialog();
    const suggested = flash.default_password === true;

    useEffect(() => {
        if (!suggested) {
            return;
        }

        toast.warning(t('login.default_password.title'), {
            id: TOAST_ID,
            description: t('login.default_password.description'),
            duration: Infinity,
            action: {
                label: t('login.default_password.change'),
                onClick: () => openSettings('account'),
            },
            cancel: {
                label: t('login.default_password.later'),
                onClick: () => toast.dismiss(TOAST_ID),
            },
        });
    }, [suggested, t, openSettings]);

    return null;
}
