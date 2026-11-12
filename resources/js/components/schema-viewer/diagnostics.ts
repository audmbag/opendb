import type {
    FindingCounts,
    SchemaDiagnostics,
    SchemaDoctorFinding,
    SchemaRelationship,
} from './types';

/**
 * The rule of the doctor finding a `*_id` column without constraint.
 */
export const IMPLICIT_RELATION = 'TRUSS-INT-002';

type Translate = (key: string, replacements?: Record<string, string>) => string;

/**
 * The text of a finding in the language of the interface, or the English message of the doctor for a rule it does
 * not know yet.
 */
export function findingText(
    t: Translate,
    finding: SchemaDoctorFinding,
    part: 'title' | 'hint',
): string | null {
    const key = `erd.doctor.rules.${finding.code}.${part}`;
    const text = t(key, {
        table: finding.table,
        column: finding.column ?? '',
    });

    if (text !== key) {
        return text;
    }

    return part === 'title' ? finding.message : finding.hint;
}

/**
 * The number of findings of each table for each severity.
 */
export function findingsByTable(
    diagnostics: SchemaDiagnostics | undefined,
): Record<string, FindingCounts> {
    const counts: Record<string, FindingCounts> = {};

    diagnostics?.findings.forEach((finding) => {
        counts[finding.table] ??= { error: 0, warning: 0, info: 0 };
        counts[finding.table][finding.severity] += 1;
    });

    return counts;
}

/**
 * The relationships a `*_id` column without constraint likely stands for.
 */
export function implicitRelationships(
    diagnostics: SchemaDiagnostics | undefined,
): SchemaRelationship[] {
    return (diagnostics?.findings ?? []).flatMap((finding) =>
        finding.code === IMPLICIT_RELATION && finding.references
            ? [
                  {
                      source: finding.table,
                      sourceColumn: finding.column,
                      target: finding.references.table,
                      targetColumn: finding.references.column,
                  },
              ]
            : [],
    );
}
