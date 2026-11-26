import { formatDialect, mysql, postgresql, sqlite } from 'sql-formatter';

/**
 * The keywords of MySQL, MariaDB, PostgreSQL and SQLite, written in capitals wherever they appear.
 */
const KEYWORDS = words(`
    add after all alter analyze and any as asc auto_increment autoincrement before begin between both by cascade case
    change check collate column commit constraint create cross current_date current_time current_timestamp database
    default deferrable deferred delete desc describe distinct do drop each else end engine escape except exists
    explain false fetch filter first following for foreign from full generated glob grant group having if ignore
    ilike immediate in index inner insert intersect into is isnull join key last lateral left like limit lock
    materialized match modify natural next no not notnull nothing null nulls of offset on only or order outer over
    partition pragma preceding primary range recursive references regexp rename replace restrict returning revoke
    right rollback row rows savepoint schema select sequence set show similar some start stored table temporary temp
    then ties to transaction trigger true truncate unbounded union unique unsigned update using vacuum values view
    virtual when where window with without zerofill
    bigint bigserial blob bool boolean bytea character datetime decimal double float int integer longblob longtext
    mediumint mediumtext numeric precision smallint smallserial tinyint tinytext varbinary varchar varying
`);

/**
 * The types that are also common column names: written in capitals only where they are a type, after the name of a
 * column (`created_at timestamp`) or in a cast (`AS date`).
 */
const AMBIGUOUS_TYPES = words(
    'binary bit char date enum interval json jsonb real serial text time timestamp timestamptz uuid year zone',
);

/**
 * The functions, written in capitals only when they are called: a column may be named `count` or `max`.
 */
const FUNCTIONS = words(`
    abs avg cast ceil coalesce concat count date_trunc extract floor greatest group_concat ifnull least length lower
    max min now nullif round string_agg substr substring sum trim upper
`);

function words(list: string): Set<string> {
    return new Set(list.split(/\s+/).filter(Boolean));
}

/**
 * The words after which a table is named. MySQL compares table names with their case on Linux, so the name is kept
 * as written even when it is also a keyword, like a table named `date`.
 */
const BEFORE_TABLE = new Set([
    'from',
    'join',
    'into',
    'update',
    'table',
    'references',
]);

/**
 * The keywords that may still follow those words: `CREATE TABLE IF NOT EXISTS`, `FROM ONLY`, `JOIN LATERAL`…
 */
const AFTER_TABLE_KEYWORDS = words('if not exists only lateral');

/**
 * The SQL with its keywords in capitals. String literals, quoted identifiers, comments, parameters, qualified names
 * and table names are kept as they are, so the statement still runs the same way.
 */
export function uppercaseSqlKeywords(sql: string): string {
    let result = '';
    let index = 0;
    /**
     * The previous word, null after a symbol other than a space, with whether it was a keyword.
     */
    let previous: { word: string; keyword: boolean } | null = null;
    let expectsTable = false;

    const copyUntil = (end: number) => {
        result += sql.slice(index, end);
        index = end;
    };

    while (index < sql.length) {
        const char = sql[index];
        const next = sql[index + 1];

        if (char === "'" || char === '"' || char === '`') {
            let end = index + 1;

            while (end < sql.length) {
                if (sql[end] === '\\' && char === "'") {
                    end += 2;
                } else if (sql[end] === char) {
                    // A doubled quote is an escaped quote, not the end of the literal.
                    if (sql[end + 1] === char) {
                        end += 2;
                    } else {
                        end++;
                        break;
                    }
                } else {
                    end++;
                }
            }

            copyUntil(end);
            // A quoted identifier can be followed by its type: `body` TEXT.
            previous = char === "'" ? null : { word: '', keyword: false };
            expectsTable = false;
        } else if (char === '-' && next === '-') {
            const end = sql.indexOf('\n', index);
            copyUntil(end === -1 ? sql.length : end);
        } else if (char === '/' && next === '*') {
            const end = sql.indexOf('*/', index + 2);
            copyUntil(end === -1 ? sql.length : end + 2);
        } else if (char === '$' && /^\$\w*\$/.test(sql.slice(index))) {
            // A dollar-quoted string of PostgreSQL: $$…$$ or $tag$…$tag$.
            const tag = /^\$\w*\$/.exec(sql.slice(index))![0];
            const end = sql.indexOf(tag, index + tag.length);
            copyUntil(end === -1 ? sql.length : end + tag.length);
        } else if (/[A-Za-z_]/.test(char)) {
            const word = /^[A-Za-z_][\w$]*/.exec(sql.slice(index))![0];
            const lower = word.toLowerCase();
            const before = sql[index - 1];
            const after = sql.slice(index + word.length).trimStart()[0];
            const isNamePart =
                before === '.' ||
                before === ':' ||
                before === '@' ||
                sql[index + word.length] === '.';
            const isTableName: boolean =
                expectsTable && !AFTER_TABLE_KEYWORDS.has(lower);
            const isKeyword: boolean =
                KEYWORDS.has(lower) ||
                (FUNCTIONS.has(lower) && after === '(') ||
                (AMBIGUOUS_TYPES.has(lower) &&
                    previous !== null &&
                    (!previous.keyword || previous.word === 'as'));
            const capitalized: boolean =
                isKeyword && !isNamePart && !isTableName;

            result += capitalized ? word.toUpperCase() : word;
            index += word.length;
            // `ON UPDATE` is followed by an action of a foreign key or CURRENT_TIMESTAMP, not by a table.
            const namesTable: boolean =
                BEFORE_TABLE.has(lower) &&
                !(lower === 'update' && previous?.word === 'on');
            expectsTable = isTableName
                ? false
                : namesTable ||
                  (expectsTable && AFTER_TABLE_KEYWORDS.has(lower));
            previous = isNamePart
                ? null
                : { word: lower, keyword: capitalized };
        } else {
            if (!/\s/.test(char)) {
                previous = null;
                expectsTable = false;
            }

            result += char;
            index++;
        }
    }

    return result;
}

/**
 * The Markdown of an answer with the keywords of its SQL blocks in capitals, including a block still being streamed.
 */
export function uppercaseSqlBlocks(markdown: string): string {
    return markdown.replace(
        /(```sql[^\n]*\n)([\s\S]*?)(```|$)/gi,
        (_, opening: string, code: string, closing: string) =>
            `${opening}${uppercaseSqlKeywords(code)}${closing}`,
    );
}

/**
 * The input of a tool call with the keywords of its query in capitals, as the chat shows it.
 */
export function withUppercaseQuery(input: unknown): unknown {
    if (
        input !== null &&
        typeof input === 'object' &&
        'query' in input &&
        typeof input.query === 'string'
    ) {
        return { ...input, query: uppercaseSqlKeywords(input.query) };
    }

    return input;
}

export const isMysql = (driver: string): boolean =>
    driver === 'mysql' || driver === 'mariadb';

/**
 * A table or column name as the database reads it whatever it holds: backticks for MySQL and MariaDB, double quotes
 * for PostgreSQL and SQLite, the quote doubled inside the name.
 */
export function quoteIdentifier(name: string, driver: string): string {
    const quote = isMysql(driver) ? '`' : '"';

    return `${quote}${name.replaceAll(quote, quote + quote)}${quote}`;
}

/**
 * The formatters of sql-formatter by driver. MariaDB uses the one of MySQL: the MariaDB one cuts `->>` in two.
 */
const FORMATTER_DIALECTS = {
    mysql,
    mariadb: mysql,
    pgsql: postgresql,
    sqlite,
};

/**
 * The script laid out one clause per line, its keywords in capitals. sql-formatter lays it out without touching the
 * case of the words, which it does not get right for every engine: they are put in capitals here, table names kept.
 *
 * @throws Error when sql-formatter cannot read the script.
 */
export function formatSql(sql: string, driver: string): string {
    const formatted = formatDialect(sql, {
        dialect:
            FORMATTER_DIALECTS[driver as keyof typeof FORMATTER_DIALECTS] ??
            mysql,
        keywordCase: 'preserve',
        dataTypeCase: 'preserve',
        functionCase: 'preserve',
        identifierCase: 'preserve',
        paramTypes: { named: [':'], positional: true, numbered: ['$'] },
        tabWidth: 4,
    });

    return uppercaseSqlKeywords(formatted);
}

/**
 * The statements of a script and the script as the engine reads it: its text values, quoted identifiers, comments
 * and dollar-quoted bodies (PostgreSQL) replaced by spaces, so that what they hold is never taken for SQL. A `;`
 * outside them ends a statement.
 */
function scanSql(sql: string): {
    bounds: { from: number; to: number }[];
    code: string;
} {
    const bounds: { from: number; to: number }[] = [];
    let code = '';
    let start = 0;
    let index = 0;

    const mask = (end: number) => {
        code += ' '.repeat(end - index);
        index = end;
    };

    while (index < sql.length) {
        const char = sql[index];
        const next = sql[index + 1];

        if (char === "'" || char === '"' || char === '`') {
            mask(closingQuote(sql, index, char));
        } else if (char === '-' && next === '-') {
            const end = sql.indexOf('\n', index);

            mask(end < 0 ? sql.length : end);
        } else if (char === '/' && next === '*') {
            const end = sql.indexOf('*/', index + 2);

            mask(end < 0 ? sql.length : end + 2);
        } else if (char === '$' && /^\$[A-Za-z_]*\$/.test(sql.slice(index))) {
            const tag = /^\$[A-Za-z_]*\$/.exec(sql.slice(index))![0];
            const end = sql.indexOf(tag, index + tag.length);

            mask(end < 0 ? sql.length : end + tag.length);
        } else {
            if (char === ';') {
                bounds.push({ from: start, to: index });
                start = index + 1;
            }

            code += char;
            index++;
        }
    }

    bounds.push({ from: start, to: sql.length });

    return { bounds, code };
}

/**
 * The bounds of a statement without the blank lines and comments around it, like the end of the line of the
 * previous one. Null when it holds nothing else.
 */
function trimmedBounds(
    sql: string,
    { from, to }: { from: number; to: number },
): { from: number; to: number } | null {
    const text = sql.slice(from, to);
    const leading =
        text.length -
        text.replace(/^(?:\s+|--[^\n]*(?:\n|$)|\/\*[\s\S]*?\*\/)*/, '').length;
    const trimmed = text.slice(leading).trimEnd();

    return trimmed === ''
        ? null
        : { from: from + leading, to: from + leading + trimmed.length };
}

/**
 * The bounds of the statement under the cursor, without its delimiter and the blank lines around it: the `;` of a
 * text value, a quoted identifier, a comment or a dollar-quoted body (PostgreSQL) does not end a statement.
 * A cursor right after a `;` belongs to the statement it ends. Null when the cursor is on blank lines or comments only.
 */
export function statementAt(
    sql: string,
    offset: number,
): { from: number; to: number } | null {
    const { bounds } = scanSql(sql);
    const statement =
        bounds.find(({ from, to }) => offset >= from && offset <= to + 1) ??
        bounds[bounds.length - 1];

    return trimmedBounds(sql, statement);
}

/**
 * The statements of a script, without their delimiter, each with its code as the engine reads it.
 */
export function splitStatements(sql: string): { text: string; code: string }[] {
    const { bounds, code } = scanSql(sql);

    return bounds.flatMap((statement) => {
        const trimmed = trimmedBounds(sql, statement);

        return trimmed
            ? [
                  {
                      text: sql.slice(trimmed.from, trimmed.to),
                      code: code.slice(trimmed.from, trimmed.to),
                  },
              ]
            : [];
    });
}

/**
 * The names of the `:name` parameters of a script, in their order, without the casts of PostgreSQL (`::int`) or the
 * assignments of MySQL (`:=`).
 */
export function parameterNames(sql: string): string[] {
    const names = [
        ...scanSql(sql).code.matchAll(/(?<![:\w]):([A-Za-z_]\w*)/g),
    ].map((match) => match[1]);

    return [...new Set(names)];
}

/**
 * The statements that may lose data for good: an UPDATE or a DELETE that changes every row, a DROP or a TRUNCATE.
 */
export function riskyStatements(sql: string): string[] {
    return splitStatements(sql)
        .filter(({ code }) => {
            // Only the WHERE of the statement itself counts, not the one of a subquery.
            let depth = 0;
            const topLevel = [...code]
                .map((char) => {
                    depth += char === '(' ? 1 : char === ')' ? -1 : 0;

                    return depth > 0 && char !== '(' ? ' ' : char;
                })
                .join('');

            return (
                /^\s*(?:DROP|TRUNCATE)\b/i.test(topLevel) ||
                (/^\s*(?:UPDATE|DELETE)\b/i.test(topLevel) &&
                    !/\bWHERE\b/i.test(topLevel))
            );
        })
        .map(({ text }) => text);
}

/**
 * The index after the quote closing the one at the given index, a doubled quote being part of the value.
 */
function closingQuote(sql: string, index: number, quote: string): number {
    let position = index + 1;

    while (position < sql.length) {
        if (sql[position] === '\\' && quote !== '`') {
            position += 2;
        } else if (sql[position] === quote) {
            if (sql[position + 1] !== quote) {
                return position + 1;
            }

            position += 2;
        } else {
            position++;
        }
    }

    return sql.length;
}

/**
 * A name of a table or a column as written by hand: as it is when it is a plain identifier that is not a keyword,
 * quoted by the engine otherwise.
 */
export function plainIdentifier(name: string, driver: string): string {
    return /^[A-Za-z_][A-Za-z0-9_]*$/.test(name) &&
        !KEYWORDS.has(name.toLowerCase())
        ? name
        : quoteIdentifier(name, driver);
}

/**
 * The query a table opens with in the SQL editor: its latest rows, by id or creation date when it has one.
 */
export function latestRowsQuery(
    table: string,
    columns: string[],
    driver: string,
): string {
    const order = ['id', 'created_at'].find((column) =>
        columns.includes(column),
    );

    return `SELECT * FROM ${plainIdentifier(table, driver)}${order ? ` ORDER BY ${order} DESC` : ''} LIMIT 100;`;
}
