<?php


namespace Sterzik\Ut\Exception;

use Exception;

class ParseException extends Exception
{
    /** @var int */
    private $line;

    /** @var int */
    private $column;

    public function __construct(string $message, array $position)
    {
        parent::__construct($message);
        list($this->line, $this->column) = $position;
    }

    public function getLine(): int
    {
        return $this->line;
    }

    public function getColumn(): int
    {
        return $this->column;
    }
}
