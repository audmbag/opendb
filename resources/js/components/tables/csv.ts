import Papa from 'papaparse';

export type CsvDelimiter = ',' | ';' | '|' | 'tab';

const DELIMITERS: CsvDelimiter[] = [',', ';', 'tab', '|'];

const character = (delimiter: CsvDelimiter): string =>
    delimiter === 'tab' ? '\t' : delimiter;

/**
 * Papa Parse takes the first line break for all of them: a file mixing Windows and Unix ones would keep a \r.
 */
const unixLines = (text: string): string => text.replace(/\r\n?/g, '\n');

/**
 * The lines of the beginning of a CSV file, values in quotes kept whole. Only used to preview the file: the server
 * reads it again whole.
 */
export function parseCsv(
    text: string,
    delimiter: CsvDelimiter,
    limit = 20,
): string[][] {
    return Papa.parse<string[]>(unixLines(text), {
        delimiter: character(delimiter),
        preview: limit,
        skipEmptyLines: true,
    }).data;
}

/**
 * The delimiter that cuts the first lines into the same number of values, comma when none does.
 */
export function guessDelimiter(text: string): CsvDelimiter {
    const detected = Papa.parse(unixLines(text), {
        preview: 10,
        delimitersToGuess: DELIMITERS.map(character),
    }).meta.delimiter;

    return (
        DELIMITERS.find((delimiter) => character(delimiter) === detected) ?? ','
    );
}

/**
 * Rows as CSV, with a header line: a value holding a comma, a quote or a line break is quoted.
 */
export function toCsv(header: string[], rows: string[][]): string {
    return Papa.unparse({ fields: header, data: rows }, { newline: '\n' });
}
