import { CodeIcon, PaletteIcon } from 'lucide-react';
import CardFrame from '@/components/card-frame';
import ColorThemePicker from '@/components/color-theme-picker';
import Select from '@/components/form/select';
import SettingRow from '@/components/settings/setting-row';
import SqlCodeBlock from '@/components/tables/sql-code-block';
import ThemeToggle from '@/components/theme-toggle';
import type { SyntaxTheme } from '@/hooks/use-syntax-theme';
import { SYNTAX_THEMES, useSyntaxTheme } from '@/hooks/use-syntax-theme';

const syntaxThemes = SYNTAX_THEMES.map((theme) => ({
    label: theme.label,
    value: theme.value,
}));

const PREVIEW_QUERY = `-- Les dix meilleurs clients de l'année
select c.name, count(o.id) as orders, sum(o.total) as revenue
from customers c
join orders o on o.customer_id = c.id
where o.created_at >= '2026-01-01' and o.status <> 'cancelled'
group by c.name
order by revenue desc
limit 10;`;

/**
 * The settings of this computer only, kept by the browser.
 */
export default function GeneralSettings() {
    const { syntaxTheme, updateSyntaxTheme } = useSyntaxTheme();

    return (
        <div className="flex flex-col gap-4">
            <CardFrame
                title="Apparence"
                description="L'apparence de l'interface sur cet ordinateur."
                icon={PaletteIcon}
            >
                <SettingRow
                    label="Thème"
                    description="Le thème système suit celui de votre ordinateur."
                >
                    <ThemeToggle />
                </SettingRow>
                <SettingRow
                    label="Thème de couleurs"
                    description="Toutes les couleurs de l'interface, son arrondi et sa police, en clair comme en sombre."
                    className="sm:flex-col sm:items-stretch"
                >
                    <ColorThemePicker />
                </SettingRow>
            </CardFrame>
            <CardFrame
                title="Coloration syntaxique"
                description="Les couleurs du code dans la conversation, les tables et la console SQL."
                icon={CodeIcon}
            >
                <SettingRow
                    label="Thème du code"
                    description="Chaque thème a une variante claire et une sombre, qui suivent le thème de l'interface."
                >
                    <Select
                        name="syntax_theme"
                        items={syntaxThemes}
                        value={syntaxTheme}
                        position="item-aligned"
                        onValueChange={(value) =>
                            updateSyntaxTheme(value as SyntaxTheme)
                        }
                    />
                </SettingRow>
                <SqlCodeBlock
                    code={PREVIEW_QUERY}
                    aria-label="Aperçu du thème du code"
                    className="mt-3 [&_pre]:text-xs"
                />
            </CardFrame>
        </div>
    );
}
