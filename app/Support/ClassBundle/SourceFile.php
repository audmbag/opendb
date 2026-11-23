<?php

declare(strict_types=1);

namespace App\Support\ClassBundle;

use PhpToken;

/**
 * A PHP file of the application, rewritten to run from a bundle rather than from its own path.
 *
 * Its namespace becomes a braced block, so that files can follow one another, and __DIR__ becomes the folder of the
 * file inside the application, wherever the bundle is unpacked. A file the rewriting could change the meaning of is
 * refused, with the reason: it keeps being read from its own path.
 *
 * It relies on nothing but PHP: the build reads it before any framework runs.
 */
final class SourceFile
{
    /**
     * The constant the bundle defines with the folder of the application, from which __DIR__ is rebuilt.
     */
    public const string BASE_PATH_CONSTANT = 'OPENDB_BUNDLE_BASE_PATH';

    /**
     * What a symbol of each kind is checked with before it is declared.
     *
     * @var array<string, string>
     */
    private const array EXISTENCE_CHECKS = [
        'class' => 'class_exists',
        'interface' => 'interface_exists',
        'trait' => 'trait_exists',
        'enum' => 'enum_exists',
    ];

    private const array TRIVIA = [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT];

    public private(set) bool $isStrict = false;

    private string $namespace = '';

    /**
     * @var list<string>
     */
    private array $imports = [];

    /**
     * The index of the first token after the opening tag, the declare, the namespace and the imports.
     */
    private int $bodyStart = 1;

    /**
     * @param  list<PhpToken>  $tokens
     */
    private function __construct(
        private readonly string $basePath,
        private readonly string $path,
        private readonly array $tokens,
    ) {}

    /**
     * @throws RefusedFile when the file does not start as a plain PHP file does
     */
    public static function read(string $basePath, string $path): self
    {
        $code = @file_get_contents($path);

        if ($code === false) {
            throw new RefusedFile('unreadable');
        }

        $file = new self(rtrim($basePath, '/'), $path, PhpToken::tokenize($code));
        $file->readHeader();

        return $file;
    }

    /**
     * The declaration of the one symbol of the file, inside its namespace, declared only if no other file did first.
     *
     * @param  'class'|'interface'|'trait'|'enum'  $kind
     *
     * @throws RefusedFile when the file holds anything but the declaration of this symbol
     */
    public function declaration(string $symbol, string $kind): string
    {
        $index = $this->skipPreloadHints($this->bodyStart);
        $start = $index;
        $index = $this->skipAttributesAndModifiers($this->nextSignificant($index));
        $keyword = $this->tokens[$index] ?? null;

        if (! $keyword?->is([T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM]) || strtolower($keyword->text) !== $kind) {
            throw new RefusedFile('no declaration of the '.$kind.' first');
        }

        $name = $this->tokens[$this->nextSignificant($index + 1)] ?? null;

        if (ltrim($this->namespace.'\\'.$name?->text, '\\') !== $symbol) {
            throw new RefusedFile('declares another symbol than '.$symbol);
        }

        $end = $this->closingBrace($index);
        $this->assertNothingAfter($end + 1);

        return $this->inNamespace(
            'if (! \\'.self::EXISTENCE_CHECKS[$kind].'('.var_export($symbol, true).', false)) {'.PHP_EOL
            .$this->rewrite($start, $end + 1).PHP_EOL
            .'}',
        );
    }

    /**
     * The file as a script that runs once, inside a closure of its namespace, as Composer runs its "files": a return
     * ends the closure as it ended the file. A script it requires from its own folder is written inside it.
     *
     * @throws RefusedFile when a closure would change the meaning of the file
     */
    public function script(): string
    {
        if ($this->isStrict) {
            throw new RefusedFile('strict types');
        }

        return $this->inNamespace('(static function () {'.PHP_EOL.$this->scriptBody().PHP_EOL.'})();');
    }

    /**
     * The expression of the folder of this file, from the constant of the bundle.
     */
    public function directoryExpression(): string
    {
        return self::pathExpression($this->basePath, dirname($this->path));
    }

    /**
     * The expression of a path of the application, built with the separator of the system running it, as PHP gives
     * __DIR__.
     */
    public static function pathExpression(string $basePath, string $path): string
    {
        if (! str_starts_with($path.'/', rtrim($basePath, '/').'/')) {
            throw new RefusedFile('outside of the application');
        }

        $segments = array_filter(explode('/', substr($path, strlen(rtrim($basePath, '/')))), fn (string $segment): bool => $segment !== '');

        return '(\\'.self::BASE_PATH_CONSTANT.implode('', array_map(fn (string $segment): string => '.\DIRECTORY_SEPARATOR.'.var_export($segment, true), $segments)).')';
    }

    private function readHeader(): void
    {
        if (($this->tokens[0] ?? null)?->is(T_OPEN_TAG) !== true || trim($this->tokens[0]->text) !== '<?php') {
            throw new RefusedFile('no opening tag first');
        }

        $index = $this->nextSignificant(1);

        while (($token = $this->tokens[$index] ?? null) !== null) {
            if ($token->is(T_DECLARE)) {
                $end = $this->statementEnd($index, blockEnds: true);

                if (preg_replace('/\s+/', '', $this->text($index, $end)) !== 'declare(strict_types=1)') {
                    throw new RefusedFile('another declare than strict types');
                }

                $this->isStrict = true;
            } elseif ($token->is(T_NAMESPACE)) {
                $end = $this->statementEnd($index, blockEnds: true);

                if ($this->namespace !== '' || ($this->tokens[$end]->text ?? '') !== ';') {
                    throw new RefusedFile('a braced namespace, or several');
                }

                $this->namespace = trim(substr($this->text($index, $end), strlen('namespace')));
            } elseif ($token->is(T_USE)) {
                $end = $this->statementEnd($index);
                $this->imports[] = $this->text($index, $end + 1);
            } else {
                break;
            }

            $this->bodyStart = $end + 1;
            $index = $this->nextSignificant($end + 1);
        }
    }

    private function inNamespace(string $code): string
    {
        return 'namespace '.($this->namespace === '' ? '' : $this->namespace.' ').'{'.PHP_EOL
            .($this->imports === [] ? '' : implode(PHP_EOL, $this->imports).PHP_EOL)
            .$code.PHP_EOL
            .'}'.PHP_EOL;
    }

    /**
     * The calls class_exists(Other::class) some packages make ahead of a class, only for OPcache to preload the
     * other one: the bundle declares it anyway.
     */
    private function skipPreloadHints(int $index): int
    {
        while (true) {
            $call = $this->nextSignificant($index);
            $name = $this->tokens[$call] ?? null;

            if (! in_array($name?->text, ['class_exists', '\class_exists'], true) || ($this->tokens[$this->nextSignificant($call + 1)]->text ?? '') !== '(') {
                return $index;
            }

            $end = $this->statementEnd($call);

            if (! preg_match('/^\\\\?class_exists\(\\\\?[\w\\\\]+::class\)$/', preg_replace('/\s+/', '', $this->text($call, $end)) ?? '')) {
                return $index;
            }

            $index = $end + 1;
        }
    }

    private function skipAttributesAndModifiers(int $index): int
    {
        while (($token = $this->tokens[$index] ?? null) !== null) {
            if ($token->is(T_ATTRIBUTE)) {
                $depth = 0;

                for (; $index < count($this->tokens); $index++) {
                    if ($this->tokens[$index]->is(T_ATTRIBUTE) || $this->tokens[$index]->text === '[') {
                        $depth++;
                    } elseif ($this->tokens[$index]->text === ']' && --$depth === 0) {
                        break;
                    }
                }
            } elseif (! $token->is([T_ABSTRACT, T_FINAL, T_READONLY])) {
                return $index;
            }

            $index = $this->nextSignificant($index + 1);
        }

        return $index;
    }

    /**
     * The index of the brace closing the body of the declaration starting at $index.
     */
    private function closingBrace(int $index): int
    {
        $depth = 0;

        for ($count = count($this->tokens); $index < $count; $index++) {
            $token = $this->tokens[$index];

            if ($token->text === '{' || $token->is([T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES])) {
                $depth++;
            } elseif ($token->text === '}' && --$depth === 0) {
                return $index;
            }
        }

        throw new RefusedFile('unbalanced braces');
    }

    private function assertNothingAfter(int $index): void
    {
        foreach (array_slice($this->tokens, $index) as $token) {
            if (! $token->is([...self::TRIVIA, T_CLOSE_TAG]) && ! ($token->is(T_INLINE_HTML) && trim($token->text) === '')) {
                throw new RefusedFile('code after the declaration');
            }
        }
    }

    /**
     * The body of the file, checked for what a closure would change: a constant, a namespace or an import there, the
     * path of the file itself, or the end of the code before the end of the file.
     */
    private function scriptBody(): string
    {
        $count = count($this->tokens);
        $end = $count;
        $depth = 0;

        for ($index = $this->bodyStart; $index < $count; $index++) {
            $token = $this->tokens[$index];

            if ($token->text === '{' || $token->is([T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES])) {
                $depth++;
            } elseif ($token->text === '}') {
                $depth--;
            } elseif ($token->is([T_HALT_COMPILER, T_NAMESPACE, T_DECLARE]) || ($depth === 0 && $token->is(T_CONST))) {
                throw new RefusedFile('a '.$token->text.' statement in a script');
            } elseif ($token->is(T_USE) && $depth === 0 && ($this->tokens[$this->previousSignificant($index - 1)]->text ?? '') !== ')') {
                throw new RefusedFile('an import after the code of a script');
            } elseif ($token->is(T_CLOSE_TAG)) {
                $this->assertNothingAfter($index + 1);
                $end = $index;

                break;
            } elseif ($token->is(T_INLINE_HTML) && trim($token->text) !== '') {
                throw new RefusedFile('inline HTML');
            }
        }

        return $this->rewrite($this->bodyStart, $end, inlinesScripts: true);
    }

    /**
     * The code between two tokens, with __DIR__ rebuilt from the constant of the bundle.
     */
    private function rewrite(int $start, int $end, bool $inlinesScripts = false): string
    {
        $code = '';

        for ($index = $start; $index < $end; $index++) {
            $token = $this->tokens[$index];

            if ($token->is([T_FILE, T_HALT_COMPILER])) {
                throw new RefusedFile('uses '.$token->text);
            }

            if ($inlinesScripts && $token->is([T_REQUIRE, T_INCLUDE]) && ($inlined = $this->inlinedScript($index)) !== null) {
                [$script, $index] = $inlined;
                $code .= $script;

                continue;
            }

            $code .= $token->is(T_DIR) ? $this->directoryExpression() : $token->text;
        }

        return $code;
    }

    /**
     * A "require __DIR__.'/file.php'" of a script in the same namespace, as polyfills load the code of their version of
     * PHP: the script is written in place, inside its own closure, which returns what the file returned. Its imports
     * join those of this file when no name of this file could take their meaning.
     *
     * @return array{string, int}|null the code, and the index of the last token it replaces
     */
    private function inlinedScript(int $index): ?array
    {
        $directory = $this->nextSignificant($index + 1);
        $dot = $this->nextSignificant($directory + 1);
        $literal = $this->nextSignificant($dot + 1);
        $after = $this->nextSignificant($literal + 1);

        if (! ($this->tokens[$directory] ?? null)?->is(T_DIR)
            || ($this->tokens[$dot]->text ?? '') !== '.'
            || ! ($this->tokens[$literal] ?? null)?->is(T_CONSTANT_ENCAPSED_STRING)
            || ! in_array($this->tokens[$after]->text ?? '', [';', ')'], true)
            || ! preg_match("/^'(\/[\w.\/-]+\.php)'$/", $this->tokens[$literal]->text, $matches)) {
            return null;
        }

        try {
            $script = self::read($this->basePath, dirname($this->path).$matches[1]);

            $imports = array_values(array_diff($script->imports, $this->imports));

            if ($script->isStrict || $script->namespace !== $this->namespace || ! $this->canImport($imports)) {
                return null;
            }

            $code = '(static function () {'.PHP_EOL.$script->scriptBody().PHP_EOL.'})()';
            $this->imports = [...$this->imports, ...$imports];

            return [$code, $literal];
        } catch (RefusedFile) {
            return null;
        }
    }

    /**
     * Whether imports can join those of this file: each of a single name, under an alias neither this file imports nor
     * writes as a name.
     *
     * @param  list<string>  $imports
     */
    private function canImport(array $imports): bool
    {
        $aliases = [];

        foreach ([...$this->imports, ...$imports] as $import) {
            if (! preg_match('/^use\s+(?:(?:function|const)\s+)?\\\\?([\w\\\\]+)(?:\s+as\s+(\w+))?\s*;$/i', $import, $matches)) {
                return false;
            }

            $aliases[] = strtolower($matches[2] ?? '' ?: array_last(explode('\\', $matches[1])));
        }

        $new = array_slice($aliases, count($this->imports));

        if (array_intersect($new, array_slice($aliases, 0, count($this->imports))) !== [] || count(array_unique($new)) !== count($new)) {
            return false;
        }

        return ! array_any(array_slice($this->tokens, $this->bodyStart), fn (PhpToken $token): bool => $token->is([T_STRING, T_NAME_QUALIFIED]) && in_array(strtolower(explode('\\', $token->text)[0]), $new, true));
    }

    private function nextSignificant(int $index): int
    {
        while (($this->tokens[$index] ?? null)?->is(self::TRIVIA)) {
            $index++;
        }

        return $index;
    }

    private function previousSignificant(int $index): int
    {
        while ($index > 0 && $this->tokens[$index]->is(self::TRIVIA)) {
            $index--;
        }

        return $index;
    }

    /**
     * The index of the semicolon ending the statement starting at $index, or of the brace opening its block: the
     * braces of an import, as in "use A\{B, C};", belong to the statement.
     */
    private function statementEnd(int $index, bool $blockEnds = false): int
    {
        for ($count = count($this->tokens); $index < $count; $index++) {
            if ($this->tokens[$index]->text === ';' || ($blockEnds && $this->tokens[$index]->text === '{')) {
                return $index;
            }
        }

        throw new RefusedFile('unfinished statement');
    }

    private function text(int $start, int $end): string
    {
        return implode('', array_map(fn (PhpToken $token): string => $token->text, array_slice($this->tokens, $start, $end - $start)));
    }
}
