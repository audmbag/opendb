import Input from '@/components/form/input';
import Select from '@/components/form/select';
import Switch from '@/components/form/switch';
import Textarea from '@/components/form/textarea';
import { cn } from '@/lib/utils';
import type { RecordFormField } from '@/types/databases';

export type RecordValue = string | boolean;

type Props = {
    fields: RecordFormField[];
    values: Record<string, RecordValue>;
    errors: Partial<Record<string, string>>;
    onChange: (field: string, value: RecordValue) => void;
    /**
     * Two columns of fields when there is room, one otherwise.
     */
    className?: string;
};

const INPUT_TYPES: Partial<Record<RecordFormField['type'], string>> = {
    integer: 'number',
    decimal: 'number',
    date: 'date',
    datetime: 'datetime-local',
    time: 'time',
};

/**
 * The value a field starts with: the one already known, else an empty value so that the column default applies.
 */
export const initialRecordValue = (
    field: RecordFormField,
    knownValues: Record<string, unknown> = {},
): RecordValue => {
    const known = knownValues[field.name];

    if (field.type === 'boolean') {
        return known !== undefined && known !== null
            ? Boolean(known)
            : ['1', 'true'].includes(String(field.default).toLowerCase());
    }

    if (known === undefined || known === null) {
        return '';
    }

    return typeof known === 'object' ? JSON.stringify(known) : String(known);
};

export const initialRecordValues = (
    fields: RecordFormField[],
    knownValues: Record<string, unknown> = {},
): Record<string, RecordValue> =>
    Object.fromEntries(
        fields.map((field) => [
            field.name,
            initialRecordValue(field, knownValues),
        ]),
    );

const fieldDescription = (field: RecordFormField): string =>
    [
        field.db_type,
        field.unique ? 'unique' : null,
        field.references
            ? `→ ${field.references.table}.${field.references.column}`
            : null,
    ]
        .filter(Boolean)
        .join(' · ');

/**
 * The fields of the form adding a row to a table, one for each column, built from its type and constraints.
 */
export default function RecordFormFields({
    fields,
    values,
    errors,
    onChange,
    className,
}: Props) {
    const renderField = (field: RecordFormField) => {
        const value = values[field.name] ?? '';
        const label = `${field.name}${field.required ? ' *' : ''}`;
        const error = errors[field.name];
        const placeholder =
            field.default !== null && field.default !== ''
                ? `Défaut : ${field.default}`
                : undefined;

        switch (field.type) {
            case 'boolean':
                return (
                    <Switch
                        key={field.name}
                        name={field.name}
                        label={label}
                        description={fieldDescription(field)}
                        error={error}
                        orientation="horizontal"
                        className="rounded-md border p-3"
                        checked={value === true}
                        onCheckedChange={(checked) =>
                            onChange(field.name, checked)
                        }
                    />
                );
            case 'enum':
                return (
                    <Select
                        key={field.name}
                        name={field.name}
                        label={label}
                        description={fieldDescription(field)}
                        error={error}
                        value={String(value)}
                        onValueChange={(selected) =>
                            onChange(field.name, selected)
                        }
                        items={(field.options ?? []).map((option) => ({
                            label: option,
                            value: option,
                        }))}
                    />
                );
            case 'text':
            case 'json':
                return (
                    <Textarea
                        key={field.name}
                        name={field.name}
                        label={label}
                        description={fieldDescription(field)}
                        error={error}
                        placeholder={
                            placeholder ??
                            (field.type === 'json' ? '{ }' : undefined)
                        }
                        className={cn(
                            '@md:col-span-2',
                            field.type === 'json' && '[&_textarea]:font-mono',
                        )}
                        value={String(value)}
                        onChange={(event) =>
                            onChange(field.name, event.target.value)
                        }
                    />
                );
            default:
                return (
                    <Input
                        key={field.name}
                        name={field.name}
                        label={label}
                        description={fieldDescription(field)}
                        error={error}
                        type={INPUT_TYPES[field.type] ?? 'text'}
                        step={field.type === 'decimal' ? 'any' : undefined}
                        maxLength={field.max_length ?? undefined}
                        placeholder={placeholder}
                        value={String(value)}
                        onChange={(event) =>
                            onChange(field.name, event.target.value)
                        }
                    />
                );
        }
    };

    return (
        <div className="@container">
            <div className={cn('grid gap-4 @md:grid-cols-2', className)}>
                {fields.map(renderField)}
            </div>
        </div>
    );
}
