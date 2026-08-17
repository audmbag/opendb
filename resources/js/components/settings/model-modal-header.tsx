import {  } from 'react';
import { ArrowLeftIcon, AstroidIcon } from 'lucide-react';
import Button from '@/components/form/button';
import { H4 } from '@/components/typograpfy/typography';
import type { Provider } from '@/types/models/provider';


export default function ModelModalHeader({ title, provider, onBack }: { provider?: Provider, title?: string, onBack: () => void }) {
    return (
        <H4 className="flex flex-col items-start gap-4">
            <Button variant="secondary" icon={ArrowLeftIcon} onClick={onBack} />
            <span className="inline-flex items-center gap-2">
                {provider ? (
                    <img
                        src={provider.icon}
                        alt={provider.label}
                        className="h-6 w-6"
                    />
                ) : (
                    <AstroidIcon className="size-6" />
                )}
                {title}
            </span>
        </H4>
    );
}
