import { CircleQuestionMarkIcon } from 'lucide-react';
import { GitHubIcon } from '@/components/icons/icons';
import { buttonVariants } from '@/components/ui/button';

export default function SocialButtons() {
    return (
        <div className="flex items-center justify-center gap-2">
            <a
                target="_blank"
                href="https://github.com/audmbag/opendb"
                className={buttonVariants({
                    size: 'icon-lg',
                    variant: 'secondary',
                })}
            >
                <GitHubIcon />
            </a>
            <a
                target="_blank"
                href="https://opendb.com/docs"
                className={buttonVariants({
                    size: 'icon-lg',
                    variant: 'secondary',
                })}
            >
                <CircleQuestionMarkIcon />
            </a>
        </div>
    );
}
