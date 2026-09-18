import type { LucideIcon } from 'lucide-react';
import { CheckIcon } from 'lucide-react';
import React, { useState } from "react"
import Button from '@/components/form/button';
import {
    Stepper,
    StepperContent,
    StepperDescription,
    StepperIndicator,
    StepperItem,
    StepperNav,
    StepperPanel,
    StepperSeparator,
    StepperTitle,
    StepperTrigger,
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

export default function Wizard({ steps, className, ...props }: WizardProps) {
    return (
        <Stepper
            className={cn('w-full space-y-8', className)}
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
                            <StepperIndicator>{index + 1}</StepperIndicator>
                            <StepperTitle>{step.header.title}</StepperTitle>
                            <StepperDescription>
                                {step.header.description}
                            </StepperDescription>
                        </StepperTrigger>
                        {steps.length > index + 1 && (
                            <StepperSeparator className="absolute inset-x-0 top-2.5 left-[calc(50%+0.875rem)] m-0 group-data-[orientation=horizontal]/stepper-nav:w-[calc(100%-2rem+0.225rem)] group-data-[orientation=horizontal]/stepper-nav:flex-none group-data-[state=completed]/step:bg-primary" />
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
