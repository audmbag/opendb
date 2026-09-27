import type { LucideIcon} from 'lucide-react';
import { Table2Icon } from 'lucide-react';
import { CheckIcon } from 'lucide-react';
import React from "react"
import IconStackGlow from '@/components/icon-stack-glow';
import {
    Stepper,
    StepperContent,
    StepperDescription,
    StepperItem,
    StepperNav,
    StepperPanel,
    StepperSeparator,
    StepperTitle,
    StepperTrigger, useStepItem,
} from '@/components/reui/stepper';
import { cn } from '@/lib/utils';



export interface WizardStep {
    id: string;
    header: {
        title: string;
        icon?: LucideIcon;
        description?: string;
    };
    content: React.ReactNode;
}

export type WizardProps = {
    steps: WizardStep[];
} & React.ComponentProps<typeof Stepper>;


const StepperIndicator = ({ icon } : { icon: LucideIcon | React.ReactElement }) => {

    const { state } = useStepItem()

    return (
        <IconStackGlow
            icon={icon}
            className={cn('z-50', {
                'text-primary-foreground after:bg-primary backdrop-blur-xs': state === 'active' || state === 'completed',
            })}
        />
    );
};

export default function Wizard({ steps, className, ...props }: WizardProps) {
    return (
        <Stepper
            className={cn('w-full space-y-4', className)}
            indicators={{
                completed: <CheckIcon className="size-4" />,
            }}
            {...props}
        >
            <StepperNav>
                {steps.map((step, index) => (
                    <StepperItem
                        key={index}
                        step={index + 1}
                        className="relative flex-1 items-start"
                    >
                        <StepperTrigger
                            asChild
                            className="flex flex-col items-center gap-2.5 text-center"
                        >
                            <StepperIndicator icon={step.header.icon || <span className="text-sm font-medium">{index + 1}</span>} />
                            <StepperTitle className="flex items-center justify-center gap-2">
                                {step.header.title}
                            </StepperTitle>
                            <StepperDescription>
                                {step.header.description}
                            </StepperDescription>
                        </StepperTrigger>
                        {steps.length > index + 1 && (
                            <StepperSeparator className="absolute inset-x-0 top-5 left-[calc(50%+0.875rem)] m-0 group-data-[orientation=horizontal]/stepper-nav:w-[calc(100%-2rem+0.225rem)] group-data-[orientation=horizontal]/stepper-nav:flex-none group-data-[state=completed]/step:bg-primary" />
                        )}
                    </StepperItem>
                ))}
            </StepperNav>

            <StepperPanel className="text-sm">
                {steps.map((step, index) => (
                    <StepperContent key={step.id} value={index + 1}>
                        {step.content}
                    </StepperContent>
                ))}
            </StepperPanel>
        </Stepper>
    );
}
