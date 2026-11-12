import { CircleQuestionMarkIcon } from 'lucide-react';
import { GitHubIcon } from '@/components/icons/icons';
import { buttonVariants } from '@/components/ui/button';
import { DOCUMENTATION_URL, REPOSITORY_URL } from '@/lib/links';

export default function SocialButtons() {
    return (
        <div className="flex items-center justify-center gap-2">
            <a
                target="_blank"
                href={REPOSITORY_URL}
                className={buttonVariants({
                    size: 'icon-lg',
                    variant: 'secondary',
                })}
            >
                <GitHubIcon />
            </a>
            <a
                target="_blank"
                href={DOCUMENTATION_URL}
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
