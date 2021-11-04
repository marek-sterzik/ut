<?php

namespace Sterzik\Ut;

class Position
{
    /** @var int */
    private $line;

    /** @var int */
    private $column;

    public function __construct(int $line, int $column)
    {
        $this->line = $line;
        $this->column = $column;
    }

    public function getLine(): int
    {
        return $this->line;
    }

    public function getColumn(): int
    {
        return $this->column;
    }

    public function __toString(): string
    {
        return sprintf("%d:%d", $this->line, $this->column);
    }
}
