import type { LucideIcon } from 'lucide-react';
import React, { useState } from "react"
import {
    Stepper,
    StepperContent,
    StepperIndicator,
    StepperItem,
    StepperNav,
    StepperPanel,
    StepperSeparator,
    StepperTrigger,
} from "@/components/reui/stepper"

import { Button } from "@/components/ui/button"


interface WizardItem {
    id: string;
    header: {
        title: string
        icon: LucideIcon
    };
    content: React.ReactNode;
}

export type WizardProps = {
    steps: WizardItem[];
};

export default function Wizard({ steps }: WizardProps) {
    const [currentStep, setCurrentStep] = useState(1);

    return (
        <Stepper
            value={currentStep}
            onValueChange={setCurrentStep}
            className="w-full max-w-lg space-y-8"
        >
            <StepperNav>
                {steps.map((step, key) => (
                    <StepperItem key={step.id} step={key + 1}>
                        <StepperTrigger asChild>
                            <StepperIndicator className="data-[state=active]:text-accent-foreground data-[state=active]:border data-[state=active]:border-accent-foreground data-[state=active]:bg-transparent data-[state=completed]:bg-accent-foreground data-[state=completed]:text-accent data-[state=inactive]:text-gray-500">
                                <step.header.icon className="size-4" />
                            </StepperIndicator>
                        </StepperTrigger>
                        {steps.length >= key && (
                            <StepperSeparator className="group-data-[state=completed]/step:bg-accent-foreground" />
                        )}
                    </StepperItem>
                ))}
            </StepperNav>

            <StepperPanel className="text-sm">
                {steps.map((step, key) => (
                    <StepperContent key={step.id} value={key + 1}>
                        {step.content}
                    </StepperContent>
                ))}
            </StepperPanel>

            <div className="flex items-center justify-between gap-2.5">
                <Button
                    variant="outline"
                    onClick={() => setCurrentStep((prev) => prev - 1)}
                    disabled={currentStep === 1}
                >
                    Previous
                </Button>
                <Button
                    variant="outline"
                    onClick={() => setCurrentStep((prev) => prev + 1)}
                    disabled={currentStep === steps.length}
                >
                    Next
                </Button>
            </div>
        </Stepper>
    );
}
