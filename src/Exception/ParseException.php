<?php


namespace Sterzik\Ut\Exception;

use Exception;

class ParseException extends Exception
{
    /** @var int */
    private $utLine;
    
    /** @var int */
    private $utColumn;

    public function __construct(string $message, int $utLine, int $utColumn)
    {
        parent::__construct($message);
        $this->utLine = $utLine;
        $this->utColumn = $utColumn;
    }

    public function getPosition(): string
    {
        return sprintf("%d:%d", $this->utLine, $this->utColumn);
    }

    public function getUtLine(): int
    {
        return $this->utLine;
    }

    public function getUtColumn(): int
    {
        return $this->utColumn;
    }
}
