import type { ColumnTransformation } from '@/components/cloning/cloning.types';

export type ColumnProps = {
    table: string;
    column: string;
};

export type StrategyOptionsProps = ColumnProps & {
    transformation: ColumnTransformation;
};
