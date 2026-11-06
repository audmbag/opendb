import { router, useHttp, usePage } from '@inertiajs/react';
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

const accessModes = [
    { value: 'read-only', label: 'Lecture seule' },
    { value: 'read-write', label: 'Lecture-écriture' },
];

const efforts = [
    { value: 'default', label: 'Standard' },
    { value: 'high', label: 'Élevé' },
];

/**
 * How the agent behaves by default: the mode and effort of a new conversation, and the limits of its work.
 */
export default function AgentSettings() {
    const { auth } = usePage().props;

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
                title="Agent IA"
                description="Le comportement de l'agent dans chaque nouvelle conversation."
                icon={BotIcon}
                actions={
                    <Button
                        type="submit"
                        size="sm"
                        icon={SaveIcon}
                        processing={form.processing}
                        disabled={!form.isDirty}
                    >
                        Enregistrer
                    </Button>
                }
            >
                <SettingRow
                    label="Mode d'accès par défaut"
                    description="En lecture seule, l'agent ne peut rien modifier dans la base. Vous pouvez toujours changer de mode dans la conversation."
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
                    label="Effort par défaut"
                    description="Un effort élevé laisse le modèle réfléchir plus longtemps, quand il le permet : plus lent et plus coûteux."
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
                    label="Étapes par réponse"
                    htmlFor="maxSteps"
                    description={`Le nombre maximal d'appels d'outils pour répondre à un message (${MAX_STEPS.min} à ${MAX_STEPS.max}).`}
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
                    label="Lignes lues par requête"
                    htmlFor="maxRows"
                    description={`Le nombre maximal de lignes qu'une requête renvoie au modèle (${MAX_ROWS.min} à ${MAX_ROWS.max}). Plus il est élevé, plus la réponse coûte de jetons.`}
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
