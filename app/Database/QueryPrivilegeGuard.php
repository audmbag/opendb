<?php

namespace App\Database;

use App\Database\Exceptions\PrivilegeException;
use App\Enums\PrivilegeEnum;
use App\Enums\PrivilegeLevelEnum;
use App\Services\Privileges\Data\TablePrivileges;
use Closure;

/**
 * Rejects the SQL the privileges of the user do not allow, like MySQL checks a statement against the privileges of its account.
 *
 * Like the other guards, it is a syntax guard, not a SQL parser, and it refuses whenever it cannot tell. A user holding the
 * privileges of a statement on the whole project runs it untouched. A user holding privileges on some tables only must hold
 * them on every table the query spells: the tables a statement changes need the privilege of the statement, and any other
 * table it spells, even in a text value, needs SELECT. A view is checked by its own name, not by the tables it reads.
 */
class QueryPrivilegeGuard
{
    /**
     * The comments and spaces before the first keyword.
     */
    private const string LEADING_NOISE = '/^(?:\s+|--[^\n]*(?:\n|$)|#[^\n]*(?:\n|$)|\/\*.*?\*\/)+/s';

    /**
     * The EXPLAIN keyword and its options, after which the statement is the one explained.
     */
    private const string EXPLAIN = '/^EXPLAIN\b(?:\s+(?:QUERY\s+PLAN|ANALY[SZ]E|VERBOSE|EXTENDED|PARTITIONS|FORMAT\s*=\s*\w+)\b|\s*\([^)]*\))*\s*/i';

    /**
     * A name, quoted or not, possibly qualified by its schema.
     */
    private const string NAME = '((?:`[^`]+`|"[^"]+"|\[[^\]]+\]|[\p{L}\p{N}_$]+)(?:\s*\.\s*(?:`[^`]+`|"[^"]+"|\[[^\]]+\]|[\p{L}\p{N}_$]+))*)';

    /**
     * The words that are not a write nested in a query: the functions named like a statement, the locking reads, the actions
     * of a foreign key and the upserts, whose privileges the statement itself requires.
     */
    private const string NOT_NESTED_WRITES = '/\b(?:REPLACE|INSERT)\s*\(|\bFOR\s+(?:NO\s+KEY\s+)?UPDATE\b|\bON\s+(?:DELETE|UPDATE)\b|\bON\s+DUPLICATE\s+KEY\s+UPDATE\b|\bDO\s+UPDATE\b|\bOR\s+REPLACE\b/i';

    /**
     * The clauses after which a FROM list ends.
     */
    private const string FROM_LIST = '/\bFROM\b(.*?)(?=\b(?:WHERE|GROUP|HAVING|ORDER|LIMIT|UNION|EXCEPT|INTERSECT|WINDOW|RETURNING|SET|VALUES|FOR)\b|\)|;|$)/is';

    /**
     * @param  Closure(): list<string>  $knownTables  Every table of the database of the project, hidden ones included.
     * @param  list<string>  $schemas  The names that may qualify a table of the project, like the name of its database.
     *
     * @throws PrivilegeException when the privileges of the user do not allow the query.
     */
    public static function assertAllowed(string $sql, TablePrivileges $privileges, Closure $knownTables, string $user, array $schemas = []): void
    {
        if ($privileges->unrestricted) {
            return;
        }

        $statement = (string) preg_replace(self::EXPLAIN, '', (string) preg_replace(self::LEADING_NOISE, '', $sql), 1);
        $classification = static::classify($statement);

        if ($classification === null) {
            if (static::holdsOnProject($privileges, PrivilegeEnum::forLevel(PrivilegeLevelEnum::Project))) {
                return;
            }

            throw PrivilegeException::statement();
        }

        ['privileges' => $required, 'mode' => $mode, 'targets' => $targets] = $classification;
        $nested = static::nestedWrites($statement, $mode);

        if (static::holdsOnProject($privileges, [...$required, ...$nested, PrivilegeEnum::Select])) {
            return;
        }

        StackedStatementGuard::assertSingleStatement($statement);

        match (true) {
            (bool) preg_match(HiddenTableGuard::SYSTEM_CATALOGS, $statement) => throw PrivilegeException::systemCatalog(),
            (bool) preg_match(HiddenTableGuard::DYNAMIC_SQL, $statement) => throw PrivilegeException::dynamicSql(),
            ! static::holdsOnProject($privileges, $nested) => throw PrivilegeException::nestedWrite(),
            default => null,
        };

        $known = $knownTables();
        static::assertNoOtherDatabase($statement, $known, $schemas);

        $cited = array_values(array_filter($known, fn (string $table): bool => HiddenTableGuard::cites($statement, $table)));

        if ($mode === 'cited') {
            static::assertOnEveryTable($privileges, $required, $cited, $user);
            $targets = $cited;
        }

        if ($mode === 'project') {
            foreach ($required as $privilege) {
                if (! $privileges->allowsOnProject($privilege)) {
                    throw PrivilegeException::deniedOnProject($privilege, $user);
                }
            }
        }

        foreach ($targets as $table) {
            foreach ($mode === 'project' ? [] : $required as $privilege) {
                if (! $privileges->allows($privilege, $table)) {
                    throw PrivilegeException::denied($privilege, $user, $table);
                }
            }
        }

        if ($mode === 'inspect' && $cited === []) {
            throw PrivilegeException::systemCatalog();
        }

        // Like MySQL, a change reading the rows it changes, through a WHERE or a RETURNING, also needs SELECT on them.
        $readsTargets = $mode !== 'project' && preg_match('/\b(?:WHERE|RETURNING)\b/i', $statement);
        $targetNames = array_map(mb_strtolower(...), $targets);

        foreach ($cited as $table) {
            $isTarget = in_array(mb_strtolower($table), $targetNames, true);

            if ((! $isTarget || $readsTargets) && ! $privileges->allows(PrivilegeEnum::Select, $table)) {
                throw PrivilegeException::denied(PrivilegeEnum::Select, $user, $table);
            }
        }
    }

    /**
     * What the statement needs and on which tables: `reads` and `inspect` only read the tables they spell, `targets` changes the
     * tables it names, `cited` may change every table it spells, and `project` needs its privileges on the whole project.
     *
     * @return array{privileges: list<PrivilegeEnum>, mode: string, targets: list<string>}|null
     */
    private static function classify(string $statement): ?array
    {
        $name = self::NAME;
        $matches = [];
        $match = function (string $pattern) use ($statement, &$matches): bool {
            return (bool) preg_match($pattern, $statement, $matches);
        };
        $result = fn (array $privileges, string $mode, array $targets = []): array => [
            'privileges' => $privileges,
            'mode' => $mode,
            'targets' => array_map(static::unqualified(...), $targets),
        ];

        return match (true) {
            $match('/^(?:SELECT|WITH|VALUES|TABLE)\b/i') => $result([PrivilegeEnum::Select], 'reads'),
            $match('/^(?:SHOW|DESCRIBE|DESC|PRAGMA)\b/i') => $result([PrivilegeEnum::Select], 'inspect'),
            $match('/^INSERT\s+(?:(?:LOW_PRIORITY|DELAYED|HIGH_PRIORITY|IGNORE|OR\s+(?:ROLLBACK|ABORT|REPLACE|FAIL|IGNORE))\s+)*(?:INTO\s+)?'.$name.'/iu') => $result([
                PrivilegeEnum::Insert,
                ...(preg_match('/^INSERT\s+OR\s+REPLACE\b/i', $statement) ? [PrivilegeEnum::Delete] : []),
                ...(preg_match('/\bON\s+DUPLICATE\s+KEY\s+UPDATE\b|\bDO\s+UPDATE\b/i', $statement) ? [PrivilegeEnum::Update] : []),
            ], 'targets', [$matches[1]]),
            $match('/^REPLACE\s+(?:(?:LOW_PRIORITY|DELAYED)\s+)*(?:INTO\s+)?'.$name.'/iu') => $result([PrivilegeEnum::Insert, PrivilegeEnum::Delete], 'targets', [$matches[1]]),
            $match('/^UPDATE\s+(?:(?:LOW_PRIORITY|IGNORE|ONLY|OR\s+\w+)\s+)*'.$name.'(.*?)\bSET\b/isu') => preg_match('/,|\bJOIN\b/i', $matches[2])
                ? $result([PrivilegeEnum::Update], 'cited')
                : $result([PrivilegeEnum::Update], 'targets', [$matches[1]]),
            $match('/^DELETE\s+(?:(?:LOW_PRIORITY|QUICK|IGNORE)\s+)*FROM\s+(?:ONLY\s+)?'.$name.'(\s*(?:\.\s*\*\s*)?,)?/iu') => ($matches[2] ?? '') !== ''
                ? $result([PrivilegeEnum::Delete], 'cited')
                : $result([PrivilegeEnum::Delete], 'targets', [$matches[1]]),
            $match('/^DELETE\b/i') => $result([PrivilegeEnum::Delete], 'cited'),
            $match('/^CREATE\s+(?:OR\s+REPLACE\s+)?(?:(?:GLOBAL|LOCAL)\s+)?(?:TEMP(?:ORARY)?\s+)?(?:UNLOGGED\s+)?(?:TABLE|VIEW|MATERIALIZED\s+VIEW)\s+(?:IF\s+NOT\s+EXISTS\s+)?'.$name.'/iu') => $result([PrivilegeEnum::Create], 'project', [$matches[1]]),
            $match('/^CREATE\s+(?:UNIQUE\s+|FULLTEXT\s+|SPATIAL\s+)?INDEX\b.*?\bON\s+(?:ONLY\s+)?'.$name.'/isu') => $result([PrivilegeEnum::Index], 'targets', [$matches[1]]),
            $match('/^DROP\s+INDEX\b.*?\bON\s+'.$name.'/isu') => $result([PrivilegeEnum::Index], 'targets', [$matches[1]]),
            $match('/^DROP\s+INDEX\b/i') => $result([PrivilegeEnum::Index], 'project'),
            $match('/^ALTER\s+(?:TABLE|VIEW)\s+(?:IF\s+EXISTS\s+)?(?:ONLY\s+)?'.$name.'/iu') => $result([PrivilegeEnum::Alter], 'targets', [$matches[1]]),
            $match('/^RENAME\s+TABLE\b/i') => $result([PrivilegeEnum::Alter], 'cited'),
            $match('/^DROP\s+(?:TEMPORARY\s+)?(?:TABLE|VIEW|MATERIALIZED\s+VIEW)\s+(?:IF\s+EXISTS\s+)?(.+?)(?:\s+(?:CASCADE|RESTRICT))?\s*;?\s*$/isu'),
            $match('/^TRUNCATE\s+(?:TABLE\s+)?(?:ONLY\s+)?(.+?)(?:\s+(?:RESTART|CONTINUE)\s+IDENTITY)?(?:\s+(?:CASCADE|RESTRICT))?\s*;?\s*$/isu') => $result([PrivilegeEnum::Drop], 'targets', array_map(trim(...), explode(',', $matches[1]))),
            default => null,
        };
    }

    /**
     * The privileges of the writes a query holds besides its own statement, like a DELETE in a WITH of PostgreSQL or the
     * table a SELECT INTO creates. The text values are left out when every engine reads them alike.
     *
     * @return list<PrivilegeEnum>
     */
    private static function nestedWrites(string $statement, string $mode): array
    {
        if (! in_array($mode, ['reads', 'targets', 'cited'], true) || preg_match('/^(?:CREATE|ALTER|DROP|TRUNCATE)\b/i', $statement)) {
            return [];
        }

        $body = (string) preg_replace('/^\s*\w+/', '', StackedStatementGuard::withoutTextValues($statement))
            |> (fn (string $body): string => (string) preg_replace(self::NOT_NESTED_WRITES, ' ', $body));

        preg_match_all('/\b(INSERT|UPDATE|DELETE|REPLACE|MERGE)\b/i', $body, $matches);

        $privileges = array_merge([], ...array_map(fn (string $keyword): array => match (strtoupper($keyword)) {
            'INSERT' => [PrivilegeEnum::Insert],
            'UPDATE' => [PrivilegeEnum::Update],
            'DELETE' => [PrivilegeEnum::Delete],
            'REPLACE' => [PrivilegeEnum::Insert, PrivilegeEnum::Delete],
            default => [PrivilegeEnum::Insert, PrivilegeEnum::Update, PrivilegeEnum::Delete],
        }, $matches[1]));

        if ($mode === 'reads' && preg_match('/\bINTO\b/i', $body)) {
            $privileges[] = PrivilegeEnum::Create;
        }

        return array_values(array_unique($privileges, SORT_REGULAR));
    }

    /**
     * @param  list<PrivilegeEnum>  $required
     * @param  list<string>  $tables
     *
     * @throws PrivilegeException
     */
    private static function assertOnEveryTable(TablePrivileges $privileges, array $required, array $tables, string $user): void
    {
        if ($tables === []) {
            foreach ($required as $privilege) {
                if (! $privileges->allowsOnProject($privilege)) {
                    throw PrivilegeException::deniedOnProject($privilege, $user);
                }
            }

            return;
        }

        foreach ($required as $privilege) {
            $denied = array_find($tables, fn (string $table): bool => ! $privileges->allows($privilege, $table));

            if ($denied !== null) {
                throw count($tables) > 1 ? PrivilegeException::severalTargets($privilege) : PrivilegeException::denied($privilege, $user, $denied);
            }
        }
    }

    /**
     * Refuse the tables qualified by another database or schema than the project's, which its privileges do not describe.
     *
     * @param  list<string>  $known
     * @param  list<string>  $schemas
     *
     * @throws PrivilegeException
     */
    private static function assertNoOtherDatabase(string $statement, array $known, array $schemas): void
    {
        $name = self::NAME;

        preg_match_all('/\b(?:JOIN|INTO|UPDATE|TABLE|USING)\s+(?:ONLY\s+|LATERAL\s+)?'.$name.'/iu', $statement, $matches);
        $references = $matches[1];

        preg_match_all(self::FROM_LIST, $statement, $lists);

        foreach ($lists[1] as $list) {
            preg_match_all('/(?:^|,)\s*(?:ONLY\s+|LATERAL\s+)?'.$name.'/iu', $list, $items);
            $references = [...$references, ...$items[1]];
        }

        $allowed = array_map(mb_strtolower(...), [...$schemas, ...$known, 'public', 'main', 'temp']);

        foreach ($references as $reference) {
            $parts = static::parts($reference);

            if (count($parts) > 1 && ! in_array(mb_strtolower($parts[count($parts) - 2]), $allowed, true)) {
                throw PrivilegeException::otherDatabase($reference);
            }
        }
    }

    /**
     * @param  list<PrivilegeEnum>  $required
     */
    private static function holdsOnProject(TablePrivileges $privileges, array $required): bool
    {
        return array_all($required, fn (PrivilegeEnum $privilege): bool => $privileges->allowsOnProject($privilege));
    }

    private static function unqualified(string $name): string
    {
        return array_last(static::parts($name)) ?? $name;
    }

    /**
     * The names a qualified name is made of, without their quotes.
     *
     * @return list<string>
     */
    private static function parts(string $name): array
    {
        preg_match_all('/`([^`]+)`|"([^"]+)"|\[([^\]]+)\]|([\p{L}\p{N}_$]+)/u', $name, $matches, PREG_SET_ORDER | PREG_UNMATCHED_AS_NULL);

        return array_map(fn (array $match): string => $match[1] ?? $match[2] ?? $match[3] ?? $match[4], $matches);
    }
}
