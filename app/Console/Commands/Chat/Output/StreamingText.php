<?php

namespace App\Console\Commands\Chat\Output;

/**
 * The text of an answer as it arrives, cut into Markdown blocks: a block is only handed over once it is complete, so
 * each one can be printed rendered and never has to be written again.
 */
class StreamingText
{
    private string $buffer = '';

    /**
     * Add a piece of the answer.
     *
     * @return list<string> the blocks it completes: paragraphs, lists, tables, blocks of code
     */
    public function push(string $delta): array
    {
        $this->buffer .= $delta;
        $blocks = [];

        while (($end = $this->endOfFirstBlock()) !== null) {
            $blocks[] = rtrim(substr($this->buffer, 0, $end), "\n");
            $this->buffer = ltrim(substr($this->buffer, $end), "\n");
        }

        return $blocks;
    }

    /**
     * What is left once the answer is over, or null when every block is already handed over.
     */
    public function flush(): ?string
    {
        $rest = trim($this->buffer, "\n");
        $this->buffer = '';

        return trim($rest) === '' ? null : $rest;
    }

    /**
     * The position after the blank line closing the first block. A blank line inside a block of code belongs to it.
     */
    private function endOfFirstBlock(): ?int
    {
        $offset = 0;
        $insideCode = false;
        $hasContent = false;

        while (($lineEnd = strpos($this->buffer, "\n", $offset)) !== false) {
            $line = substr($this->buffer, $offset, $lineEnd - $offset);
            $offset = $lineEnd + 1;

            if (preg_match('/^ {0,3}(```|~~~)/', $line)) {
                $insideCode = ! $insideCode;
            }

            if (trim($line) !== '') {
                $hasContent = true;
            } elseif ($hasContent && ! $insideCode) {
                return $offset;
            }
        }

        return null;
    }
}
