import { useLaravelReactI18n } from 'laravel-react-i18n';
import type { LucideIcon} from 'lucide-react';
import  { TriangleAlertIcon } from 'lucide-react';
import React from 'react';
import type { ButtonProps } from '@/components/form/button';
import type { InputPropsType } from '@/components/form/input';
import Tooltip from '@/components/tooltip';
import type { TooltipProps } from '@/components/tooltip';
import { buttonVariants } from '@/components/ui/button';
import { Field, FieldDescription, FieldLabel } from '@/components/ui/field';
import { InputGroup, InputGroupAddon } from '@/components/ui/input-group';
import {
    NativeSelect,
    NativeSelectOption,
} from '@/components/ui/native-select';
import {
    Select as UISelect,
    SelectContent as UISelectContent,
    SelectGroup,
    SelectItem,
    SelectLabel,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';

export interface SelectItemInterface<T = string> {
    label: string;
    value: T;
    icon?: LucideIcon;
    description?: string;
    descriptionTooltip?: boolean;
}


type Props = {
    label?: string;
    items: SelectItemInterface[];
    button?: boolean;
    native?: boolean;
    tooltip?: TooltipProps['value'];
    align?: React.ComponentProps<typeof UISelectContent>['align'];
    size?: ButtonProps['size'];
    variant?: ButtonProps['variant'];
    position?: React.ComponentProps<typeof UISelectContent>['position'];
} & React.ComponentProps<typeof UISelect> &
    Omit<React.ComponentProps<typeof NativeSelect>, 'size'>;




const SelectContent = ({ label, items, button = false, native = false, tooltip, align = "center", size = 'sm', variant = 'secondary', position = 'popper', onValueChange, ...props }: Props) => {
    const { t } = useLaravelReactI18n();

    if (native) {
        return (
            <Tooltip value={tooltip}>
                <NativeSelect
                    {...props}
                    size={size === 'sm' ? 'sm' : 'default'}
                    onChange={(event) => onValueChange?.(event.target.value)}
                    className={'w-full [&_select]:bg-transparent! [&_select]:border-0!'}
                >
                    {label && (
                        <NativeSelectOption value="" disabled>
                            {label}
                        </NativeSelectOption>
                    )}
                    {items.map((item) => (
                        <NativeSelectOption
                            key={item.value}
                            value={String(item.value)}
                        >
                            {item.label}
                        </NativeSelectOption>
                    ))}
                </NativeSelect>
            </Tooltip>
        );
    }

    return (
        <UISelect {...props} onValueChange={onValueChange}>
            <Tooltip value={tooltip}>
                <SelectTrigger
                    size={size === 'xs' ? 'xs' : undefined}
                    data-variant={button ? variant : undefined}
                    className={cn(
                        'w-full [&_.description]:hidden!',
                        button && buttonVariants({ variant, size }),
                        size === 'xs' && '[&>svg:last-child]:size-3 [&_svg]:size-3',
                        button && 'justify-between shadow-none',
                        {
                            'bg-transparent! border-0!': !button,
                        },
                    )}
                >
                    <SelectValue
                        placeholder={label || t('common.select_option')}
                    />
                </SelectTrigger>
            </Tooltip>
            <UISelectContent position={position} align={align}>
                <SelectGroup>
                    {label && <SelectLabel>{label}</SelectLabel>}
                    {items.map((item) => (
                        <Tooltip
                            value={
                                item.descriptionTooltip
                                    ? item.description
                                    : undefined
                            }
                            key={item.value}
                        >
                            <SelectItem key={item.value} value={item.value}>
                                <div className={'flex flex-col gap-1'}>
                                    <p className={'flex items-center gap-1'}>
                                        {item.icon && (
                                            <item.icon className="h-4 w-4" />
                                        )}
                                        {item.label}
                                    </p>
                                    {item.description &&
                                        !item.descriptionTooltip && (
                                            <p className="description text-sm text-muted-foreground">
                                                {item.description}
                                            </p>
                                        )}
                                </div>
                            </SelectItem>
                        </Tooltip>
                    ))}
                </SelectGroup>
            </UISelectContent>
        </UISelect>
    );
}


export default function Select({
    label,
    error,
    description,
    className,
    icon: Icon,
    optionsLabel,
    ...props
}: Omit<InputPropsType, 'button'> & Props & { optionsLabel?: string }) {
    return (
        <Field className={cn('gap-2 w-auto', className)}>
            {label && (
                <FieldLabel htmlFor={props.name || props.id}>
                    {label}
                </FieldLabel>
            )}

            <InputGroup
                className={cn({
                    'h-6': props.size === 'xs',
                    'border-0 bg-transparent shadow-none dark:bg-transparent': props.button,
                })}
            >
                {Icon && !error && (
                    <InputGroupAddon>
                        <Icon className={'size-4'} />
                    </InputGroupAddon>
                )}
                {error && (
                    <Tooltip value={error} variant="destructive">
                        <InputGroupAddon className="cursor-auto text-destructive">
                            <TriangleAlertIcon className={'size-4'} />
                        </InputGroupAddon>
                    </Tooltip>
                )}
                <SelectContent label={optionsLabel} {...props} />
            </InputGroup>
            {description && <FieldDescription>{description}</FieldDescription>}
        </Field>
    );
}
