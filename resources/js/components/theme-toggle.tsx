import { MoonIcon, SunIcon } from 'lucide-react';
import { useAppearance } from '@/hooks/use-apparence';

type Props = {

};

export default function ThemeToggle({  }: Props) {

    const { appearance, updateAppearance } = useAppearance();

    return (
        <div>
            <button onClick={() => updateAppearance(appearance === 'light' ? 'dark' : 'light')}>
                {appearance === 'light' ? <MoonIcon /> : <SunIcon />}
            </button>
        </div>
    );
}
