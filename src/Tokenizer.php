<?php

namespace Sterzik\Ut;

class Tokenizer
{
    /**
     * Parse the given string to tokens
     * @return iterable<Token>
     */
    public function tokenize(string $string): iterable
    {
        $state = new TokenizerState();
        $n = strlen($string);
        $line = 1;
        $column = 0;
        for($i = 0; $i < $n; $i++) {
            $column++;
            $char = $string[$i];
            if ($char === "\n") {
                $line++;
                $column=1;
            }
            foreach ($state->putChar($char, [$line, $column]) as $token) {
                yield $token;
            }
        }
        foreach ($state->putChar(null, [$line, $column]) as $token) {
            yield $token;
        }
    }
}
