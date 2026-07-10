'use client';

import {
    Stepper as UIStepper,
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
import { CheckIcon, LoaderCircleIcon, LucideIcon } from 'lucide-react';
import { Card, CardContent } from './ui/card';


type StepperItem = {
    title: string;
    description?: string;
    icon: LucideIcon,
    content: React.ReactNode;
}

export type StepperProps = {
    steps: StepperItem[];
    value?: number;
    onValueChange?: (value: number) => void;
} & React.HTMLAttributes<HTMLDivElement>;


export default function Stepper({ steps, value, onValueChange, ...props } : StepperProps) {
    return (
        <UIStepper
            value={value}
            onValueChange={onValueChange}
            indicators={{
                completed: <CheckIcon className="size-3.5" />,
                loading: <LoaderCircleIcon className="size-3.5 animate-spin" />,
            }}
            className={cn("w-full space-y-4 pt-2", props.className)}
        >
            <StepperNav>
                {steps.map((item, index) => (
                    <StepperItem
                        key={index}
                        step={index + 1}
                        className="relative flex-1 items-start"
                    >
                        <StepperTrigger className="flex flex-col gap-2.5">
                            <StepperIndicator className="size-10 -mt-2.5 relative z-10">
                                {<item.icon className="size-4" />}
                            </StepperIndicator>
                            <StepperTitle>{item.title}</StepperTitle>
                            <StepperDescription>
                                {item.description}
                            </StepperDescription>
                        </StepperTrigger>

                        {steps.length > index + 1 && (
                            <StepperSeparator className="absolute inset-x-0 top-2.5 left-[calc(50%+0.875rem)] m-0 group-data-[orientation=horizontal]/stepper-nav:w-[calc(100%-2rem+0.225rem)] group-data-[orientation=horizontal]/stepper-nav:flex-none group-data-[state=completed]/step:bg-primary" />
                        )}
                    </StepperItem>
                ))}
            </StepperNav>

            <StepperPanel className="text-sm">
                {steps.map((item, index) => (
                    <StepperContent
                        key={index}
                        value={index + 1}
                    >
                        <Card>
                            <CardContent>
                                {item.content}
                            </CardContent>
                        </Card>
                    </StepperContent>
                ))}
            </StepperPanel>
        </UIStepper>
    );
}
