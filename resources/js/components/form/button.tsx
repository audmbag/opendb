import React, { JSX } from "react";
import { buttonVariants, Button as UIButton } from "../ui/button";
import { VariantProps } from "class-variance-authority";
import { LucideIcon } from "lucide-react";
import { Tooltip, TooltipContent, TooltipTrigger } from "@/components/ui/tooltip";


export type ButtonProps = React.ComponentProps<"button">  & VariantProps<typeof buttonVariants> & {
    asChild?: boolean,
    icon?: LucideIcon | ((props: React.SVGProps<SVGSVGElement>) => React.ReactElement),
    tooltip?: string | (() => JSX.Element),
}



export default function Button ({asChild, icon: Icon, tooltip, ...props}: ButtonProps)  {
    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <UIButton asChild={asChild} {...props}>
                    {Icon && <Icon />}
                    <span>{props.children}</span>
                </UIButton>
            </TooltipTrigger>
            {tooltip && <TooltipContent>
                <p>{typeof tooltip === "function" ? tooltip() : tooltip}</p>
            </TooltipContent>}
        </Tooltip>
    );
}