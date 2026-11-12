import { router, useHttp, usePage } from '@inertiajs/react';
import { useLaravelReactI18n } from 'laravel-react-i18n';
import { BotIcon, SaveIcon } from 'lucide-react';
import type React from 'react';
import { toast } from 'sonner';
import CardFrame from '@/components/card-frame';
import Button from '@/components/form/button';
import Input from '@/components/form/input';
import Select from '@/components/form/select';
import SettingRow from '@/components/settings/setting-row';
import type { UserSettings } from '@/types/models/user';
import SettingsController from '@/wayfinder/actions/App/Http/Controllers/SettingsController';

const MAX_STEPS = { min: 3, max: 50 };
const MAX_ROWS = { min: 10, max: 1000 };

const ACCESS_MODES = ['read-only', 'read-write'] as const;

const EFFORTS = ['default', 'high'] as const;

const SCHEMA_APPLY_MODES = ['draft', 'additions', 'auto'] as const;

/**
 * How the agent behaves by default: the mode and effort of a new conversation, and the limits of its work.
 */
export default function AgentSettings() {
    const { t } = useLaravelReactI18n();
    const { auth } = usePage().props;

    const accessModes = ACCESS_MODES.map((value) => ({
        value,
        label: t(`ai.access_modes.${value}`),
    }));
    const efforts = EFFORTS.map((value) => ({
        value,
        label: t(`ai.efforts.${value}`),
    }));

    const schemaApplyModes = SCHEMA_APPLY_MODES.map((value) => ({
        value,
        label: t(`erd.assistant.mode.${value}`),
    }));

    const form = useHttp<UserSettings['agent'], { message: string }>({
        ...auth.user.settings.agent,
    });

    const save = (event: React.FormEvent) => {
        event.preventDefault();

        form.put(SettingsController.updateAgent.url(), {
            onSuccess: (response) => {
                toast.success(response.message);
                form.setDefaults();
                router.reload({ only: ['auth'] });
            },
        });
    };

    return (
        <form onSubmit={save}>
            <CardFrame
                title={t('settings.agent.title')}
                description={t('settings.agent.description')}
                icon={BotIcon}
                actions={
                    <Button
                        type="submit"
                        size="sm"
                        icon={SaveIcon}
                        processing={form.processing}
                        disabled={!form.isDirty}
                    >
                        {t('common.save')}
                    </Button>
                }
            >
                <SettingRow
                    label={t('settings.agent.access_mode')}
                    description={t('settings.agent.access_mode_description')}
                >
                    <Select
                        name="accessMode"
                        items={accessModes}
                        value={form.data.accessMode}
                        onValueChange={(value) =>
                            form.setData(
                                'accessMode',
                                value as UserSettings['agent']['accessMode'],
                            )
                        }
                        error={form.errors.accessMode}
                    />
                </SettingRow>
                <SettingRow
                    label={t('settings.agent.effort')}
                    description={t('settings.agent.effort_description')}
                >
                    <Select
                        name="effort"
                        items={efforts}
                        value={form.data.effort}
                        onValueChange={(value) =>
                            form.setData(
                                'effort',
                                value as UserSettings['agent']['effort'],
                            )
                        }
                        error={form.errors.effort}
                    />
                </SettingRow>
                <SettingRow
                    label={t('settings.agent.schema_apply_mode')}
                    description={t(
                        'settings.agent.schema_apply_mode_description',
                    )}
                >
                    <Select
                        name="schemaApplyMode"
                        items={schemaApplyModes}
                        value={form.data.schemaApplyMode}
                        onValueChange={(value) =>
                            form.setData(
                                'schemaApplyMode',
                                value as UserSettings['agent']['schemaApplyMode'],
                            )
                        }
                        error={form.errors.schemaApplyMode}
                    />
                </SettingRow>
                <SettingRow
                    label={t('settings.agent.max_steps')}
                    htmlFor="maxSteps"
                    description={t(
                        'settings.agent.max_steps_description',
                        MAX_STEPS,
                    )}
                >
                    <Input
                        id="maxSteps"
                        name="maxSteps"
                        type="number"
                        className="w-28"
                        min={MAX_STEPS.min}
                        max={MAX_STEPS.max}
                        value={String(form.data.maxSteps)}
                        onChange={(event) =>
                            form.setData('maxSteps', Number(event.target.value))
                        }
                        error={form.errors.maxSteps}
                    />
                </SettingRow>
                <SettingRow
                    label={t('settings.agent.max_rows')}
                    htmlFor="maxRows"
                    description={t(
                        'settings.agent.max_rows_description',
                        MAX_ROWS,
                    )}
                >
                    <Input
                        id="maxRows"
                        name="maxRows"
                        type="number"
                        className="w-28"
                        min={MAX_ROWS.min}
                        max={MAX_ROWS.max}
                        value={String(form.data.maxRows)}
                        onChange={(event) =>
                            form.setData('maxRows', Number(event.target.value))
                        }
                        error={form.errors.maxRows}
                    />
                </SettingRow>
            </CardFrame>
        </form>
    );
}
