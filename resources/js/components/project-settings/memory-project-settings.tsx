import { useHttp, usePage } from '@inertiajs/react';
import {
    EraserIcon,
    LoaderIcon,
    NotebookPenIcon,
    PlusIcon,
    SaveIcon,
    Trash2Icon,
} from 'lucide-react';
import type React from 'react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import CardFrame from '@/components/card-frame';
import { EmptyState } from '@/components/empty-state';
import Button from '@/components/form/button';
import Input from '@/components/form/input';
import { Badge } from '@/components/reui/badge';
import { Muted } from '@/components/typograpfy/typography';
import type { Project } from '@/types/models/project';
import ProjectSettingsController from '@/wayfinder/actions/App/Http/Controllers/Project/ProjectSettingsController';

type MemoryEntry = {
    date: string | null;
    note: string;
};

type MemoryResponse = {
    data: MemoryEntry[];
    max?: number;
    message?: string;
};

/**
 * What the agent remembers about the project between its conversations, injected in its instructions.
 */
export default function MemoryProjectSettings() {
    const { project } = usePage<{ project: Project }>().props;

    const [entries, setEntries] = useState<MemoryEntry[]>([]);
    const [max, setMax] = useState(100);
    const [newNote, setNewNote] = useState('');
    const [isDirty, setIsDirty] = useState(false);

    const loader = useHttp<null, MemoryResponse>();
    const form = useHttp<{ entries: MemoryEntry[] }, MemoryResponse>({
        entries: [],
    });

    useEffect(() => {
        loader.get(ProjectSettingsController.memory.url(project.id), {
            onSuccess: (response) => {
                setEntries(response.data);
                setMax(response.max ?? 100);
            },
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [project.id]);

    form.transform(() => ({ entries }));

    const change = (update: (current: MemoryEntry[]) => MemoryEntry[]) => {
        setEntries(update);
        setIsDirty(true);
    };

    const addEntry = (event: React.FormEvent) => {
        event.preventDefault();

        if (newNote.trim() === '') {
            return;
        }

        change((current) => [...current, { date: null, note: newNote.trim() }]);
        setNewNote('');
    };

    const save = () => {
        form.put(ProjectSettingsController.updateMemory.url(project.id), {
            onSuccess: (response) => {
                toast.success(response.message);
                setEntries(response.data);
                setIsDirty(false);
            },
        });
    };

    if (loader.processing) {
        return (
            <div className="flex justify-center py-8">
                <LoaderIcon className="animate-spin" />
            </div>
        );
    }

    return (
        <CardFrame
            title="Mémoire de l'agent"
            description="Les faits que l'agent retient sur ce projet d'une conversation à l'autre. Il les relit au début de chaque conversation."
            icon={NotebookPenIcon}
            badge={
                <Badge variant="secondary">{`${entries.length} / ${max}`}</Badge>
            }
            actions={
                <>
                    <Button
                        variant="ghost"
                        size="sm"
                        icon={EraserIcon}
                        disabled={entries.length === 0}
                        onClick={() => change(() => [])}
                        confirmation={{
                            title: 'Vider la mémoire',
                            description:
                                "Toutes les entrées seront retirées à l'enregistrement. L'agent oubliera ce qu'il savait du projet.",
                            confirmText: 'Vider',
                            icon: EraserIcon,
                        }}
                    >
                        Tout effacer
                    </Button>
                    <Button
                        icon={SaveIcon}
                        size="sm"
                        processing={form.processing}
                        disabled={!isDirty}
                        onClick={save}
                    >
                        Enregistrer
                    </Button>
                </>
            }
        >
            <div className="flex flex-col gap-4">
                {entries.length === 0 ? (
                    <EmptyState
                        title="Mémoire vide"
                        icon={NotebookPenIcon}
                        description="L'agent n'a encore rien retenu. Il ajoute des entrées quand il apprend quelque chose d'utile, ou vous pouvez en écrire ici."
                    />
                ) : (
                    <div className="flex max-h-80 flex-col gap-2 overflow-y-auto">
                        {entries.map((entry, index) => (
                            <div key={index} className="flex items-start gap-2">
                                <Input
                                    className="flex-1"
                                    name={`entries.${index}.note`}
                                    value={entry.note}
                                    aria-label={`Entrée ${index + 1} de la mémoire`}
                                    onChange={(event) =>
                                        change((current) =>
                                            current.map((item, itemIndex) =>
                                                itemIndex === index
                                                    ? {
                                                          ...item,
                                                          note: event.target
                                                              .value,
                                                      }
                                                    : item,
                                            ),
                                        )
                                    }
                                    error={
                                        form.errors[
                                            `entries.${index}.note` as keyof typeof form.errors
                                        ]
                                    }
                                />
                                <Muted className="w-22 shrink-0 pt-2 text-xs">
                                    {entry.date ?? 'Nouvelle'}
                                </Muted>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    icon={Trash2Icon}
                                    tooltip="Retirer cette entrée"
                                    onClick={() =>
                                        change((current) =>
                                            current.filter(
                                                (_, itemIndex) =>
                                                    itemIndex !== index,
                                            ),
                                        )
                                    }
                                />
                            </div>
                        ))}
                    </div>
                )}
                {form.errors.entries && (
                    <Muted className="text-xs text-destructive">
                        {form.errors.entries}
                    </Muted>
                )}
                <form className="flex items-start gap-2" onSubmit={addEntry}>
                    <Input
                        className="flex-1"
                        name="newNote"
                        placeholder="Ex : les montants de la table orders sont en centimes."
                        value={newNote}
                        onChange={(event) => setNewNote(event.target.value)}
                    />
                    <Button
                        type="submit"
                        variant="outline"
                        icon={PlusIcon}
                        disabled={
                            newNote.trim() === '' || entries.length >= max
                        }
                    >
                        Ajouter
                    </Button>
                </form>
            </div>
        </CardFrame>
    );
}
