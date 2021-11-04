<?php

namespace Sterzik\Ut;

use Generator;

class Tokenizer
{
    /**
     * Parse the given string to tokens
     * @return Generator<Token>
     */
    public function tokenize(string $string): Generator
    {
        $state = new TokenizerState();
        $n = strlen($string);
        $line = 1;
        $column = 0;
        for ($i = 0; $i < $n; $i++) {
            $column++;
            $char = $string[$i];
            if ($char === "\n") {
                $line++;
                $column=1;
            }
            foreach ($state->putChar($char, new Position($line, $column)) as $token) {
                yield $token;
            }
        }
        foreach ($state->putChar(null, new Position($line, $column)) as $token) {
            yield $token;
        }
    }
}
