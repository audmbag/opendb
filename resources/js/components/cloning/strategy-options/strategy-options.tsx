import React from 'react';
import FakerOptions from '@/components/cloning/strategy-options/faker-options';
import HashOptions from '@/components/cloning/strategy-options/hash-options';
import MaskOptions from '@/components/cloning/strategy-options/mask-options';
import StaticOptions from '@/components/cloning/strategy-options/static-options';
import type { StrategyOptionsProps } from '@/components/cloning/strategy-options/types';

/**
 * Champs additionnels affichés selon la stratégie choisie.
 */
const StrategyOptions = (props: StrategyOptionsProps) => {
    switch (props.transformation.strategy) {
        case 'fake':
            return <FakerOptions {...props} />;
        case 'mask':
            return <MaskOptions {...props} />;
        case 'hash':
            return <HashOptions {...props} />;
        case 'static':
            return <StaticOptions {...props} />;
        default:
            return null;
    }
};

export default StrategyOptions;
