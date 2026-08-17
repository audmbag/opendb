import type { LucideIcon } from 'lucide-react';
import { LaptopMinimalIcon } from 'lucide-react';
import { SunIcon } from 'lucide-react';
import CardFrame from '@/components/card-frame';
import { H6 } from '@/components/typograpfy/typography';
import {
    Field,
    FieldContent,
    FieldLabel,
    FieldTitle,
} from '@/components/ui/field';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import type { Appearance } from '@/hooks/use-apparence';
import { useAppearance } from '@/hooks/use-apparence';

const themes: {
    id: Appearance;
    label: string;
    icon: LucideIcon;
    illustration: string;
}[] = [
    {
        id: 'light',
        label: 'Clair',
        icon: SunIcon,
        illustration: '/assets/images/light.png',
    },
    {
        id: 'dark',
        label: 'Sombre',
        icon: SunIcon,
        illustration: '/assets/images/dark.png',
    },
    {
        id: 'system',
        label: 'Système',
        icon: LaptopMinimalIcon,
        illustration: '/assets/images/system.png',
    },
];

export default function ThemesSettings() {

    const { appearance, updateAppearance } = useAppearance();

    return (
        <div>
            <div className="flex flex-col gap-4">
                <H6>Thème</H6>
                <RadioGroup
                    value={appearance}
                    onValueChange={updateAppearance}
                    className="flex max-w-lg gap-2"
                    defaultValue={appearance}
                >
                    {themes.map((theme) => (
                        <FieldLabel
                            key={theme.id}
                            htmlFor={theme.id}
                            className="cursor-pointer"
                        >
                            <Field orientation="horizontal">
                                <FieldContent>
                                    <div className="pb-2">
                                        <img
                                            src={theme.illustration}
                                            alt={theme.label}
                                            className="rounded-sm"
                                        />
                                    </div>
                                    <FieldTitle className="flex items-center gap-2">
                                        <theme.icon className="size-4" />
                                        <span>{theme.label}</span>
                                    </FieldTitle>
                                </FieldContent>
                                <RadioGroupItem
                                    value={theme.id}
                                    id={theme.id}
                                    className="hidden"
                                />
                            </Field>
                        </FieldLabel>
                    ))}
                </RadioGroup>
            </div>
        </div>
    );
}
