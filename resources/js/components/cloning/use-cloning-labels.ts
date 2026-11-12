import { useLaravelReactI18n } from 'laravel-react-i18n';

/**
 * The labels of the anonymization, from lang/{locale}/cloning.php: the strategies, the Faker methods and their
 * parameters, the detected personal data. A value without translation is shown as is.
 */
export function useCloningLabels() {
    const { t } = useLaravelReactI18n();

    const labelOf = (key: string, fallback: string): string => {
        const label = t(key);

        return label === key ? fallback : label;
    };

    return {
        strategy: (strategy: string) => labelOf(`cloning.strategies.${strategy}`, strategy),
        fakerMethod: (method: string) => labelOf(`cloning.faker.methods.${method}`, method),
        fakerParameter: (parameter: string) => labelOf(`cloning.faker.parameters.${parameter}`, parameter),
        fakerParameterOption: (option: string) => labelOf(`cloning.faker.options.${option}`, option),
        matcher: (matcher: string) => labelOf(`cloning.matchers.${matcher}`, matcher),
        group: (group: string) => labelOf(`cloning.groups.${group}`, group),
    } as const;
}
