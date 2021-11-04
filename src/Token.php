<?php

namespace Sterzik\Ut;

class Token
{
    const TYPE_DATA = "data";
    const TYPE_DOLLAR = "dollar";
    const TYPE_VAR = "var";
    const TYPE_START_EXPR = "start-expr";
    const TYPE_DELIMETER = "delimeter";
    const TYPE_IDENTIFIER = "identifier";
    const TYPE_STRING = "string";
    const TYPE_OPERATOR = "operator";
    const TYPE_INTEGER = "integer";
    const TYPE_FLOAT = "float";
    const TYPE_INVALID = "invalid";
    const TYPE_WHITE = "white";
    const TYPE_EOF = "eof";

    /** @var callable */
    private $changeStateCallback;

    /** @var string */
    private $type;

    /** @var string */
    private $data;

    /** @var int */
    private $line;

    /** @var int */
    private $column;

    public function __construct(callable $changeStateCallback, string $type, string $data, array $position)
    {
        $this->changeStateCallback = $changeStateCallback;
        $this->type = $type;
        $this->data = $data;
        list($this->line, $this->column) = $position;
    }

    public function setState(string $state): self
    {
        $callback = $this->changeStateCallback;
        $callback($state);
        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getData(): string
    {
        return $this->data;
    }

    public function __toString()
    {
        return sprintf("%s: %s (%s:%s)", $this->type, json_encode($this->data), $this->line, $this->column);
    }
}

