<?php


namespace Sterzik\Ut\Exception;

use Exception;

class ParseException extends Exception
{
    /** @var array<int> */
    private $position;

    public function __construct(string $message, array $position)
    {
        parent::__construct($message);
        $this->position = $position;
    }

    public function getPosition(): array
    {
        return $this->position;
    }
}
