<?php

namespace Sterzik\Ut;

use Sterzik\Ut\Exception\ParseException;

class TokenizerState
{
    const STATE_NORMAL = "normal";
    const STATE_EXPR = "expr";

    const SUBSTATE_START = "start";
    const SUBSTATE_DATA = "data";
    const SUBSTATE_DOLLAR = "dollar";
    const SUBSTATE_VAR = "var";

    /** @var string */
    private $state;

    /** @var string */
    private $tokenString;

    /** @var array<int> */
    private $tokenFirstPosition;


    public function __construct()
    {
        $this->state = self::STATE_NORMAL;
        $this->substate = self::SUBSTATE_START;
        $this->tokenString = "";
        $this->tokenFirstPosition = [0, 0];
    }

    public function getState(): string
    {
        return $this->state;
    }

    public function setState(string $state): self
    {
        return $this;
    }

    public function putChar(?string $char, array $position): iterable
    {
        if ($this->state === self::STATE_NORMAL) {
            return $this->putCharNormal($char, $position);
        } elseif ($this->state === self::STATE_EXPR) {
            return $this->putCharExpr($char, $position);
        } else {
            throw new ParseException(sprintf("Invalid tokenizer state: %s", $this->state), $position);
        }
    }

    private function createToken(string $type, bool $flushTokenString = true): Token
    {
        $position = $this->tokenFirstPosition;
        $stateChangeCallback = function($state) use ($position) {
            if ($this->substate !== self::SUBSTATE_START || $this->tokenString !== '') {
                throw new ParseException(sprintf("Invalid tokenizer state change to: %s", $state), $position);
            }
            $this->state = $state;
        };

        $token = new Token($stateChangeCallback, $type, $this->tokenString, $position);

        if ($flushTokenString) {
            $this->tokenString = "";
        }

        return $token;
    }

    private function putCharExpr(?string $char, array $position): iterable
    {
        if ($char === '}') {
            if ($this->tokenString !== '') {
                yield $this->createToken(Token::TYPE_STRING);
            }
            $this->tokenString .= $char;
                yield $this->createToken(Token::TYPE_OPERATOR);
        } else {
            $this->tokenString .= $char;
        }
    }

    private function putCharNormal(?string $char, array $position): iterable
    {
        switch ($this->substate) {
            case self::SUBSTATE_START:
                $this->tokenFirstPosition = $position;
                if ($char === null) {
                    // do nothing
                } elseif ($char === '$') {
                    $this->substate = self::SUBSTATE_DOLLAR;
                } elseif ($char === '{') {
                    $this->tokenString .= $char;
                    yield $this->createToken(Token::TYPE_START_EXPR);
                } elseif ($char === '|') {
                    $this->tokenString .= $char;
                    yield $this->createToken(Token::DELIMETER);
                } else {
                    $this->substate = self::SUBSTATE_DATA;
                    $this->tokenString .= $char;
                }
                break;

            case self::SUBSTATE_DATA:
                if ($char === null) {
                    yield $this->createToken(Token::TYPE_DATA);
                } elseif ($char === '$') {
                    yield $this->createToken(Token::TYPE_DATA);
                    $this->substate = self::SUBSTATE_DOLLAR;
                    $this->tokenFirstPosition = $position;
                } elseif ($char === '{') {
                    yield $this->createToken(Token::TYPE_DATA);
                    $this->substate = self::SUBSTATE_START;
                    $this->tokenString .= $char;
                    $this->tokenFirstPosition = $position;
                    yield $this->createToken(Token::TYPE_START_EXPR);
                } elseif ($char === '|') {
                    yield $this->createToken(Token::TYPE_DATA);
                    $this->tokenString .= $char;
                    $this->tokenFirstPosition = $position;
                    yield $this->createToken(Token::TYPE_DELIMETER);
                } else {
                    $this->tokenString .= $char;
                }
                break;

            case self::SUBSTATE_DOLLAR:
                if ($char === null) {
                    $this->tokenString .= '$';
                    yield $this->createToken(Token::TYPE_DOLLAR);
                } elseif ($char === '$') {
                    $this->tokenString .= '$' . $char;
                    yield $this->createToken(Token::TYPE_DOLLAR);
                    $this->substate = self::SUBSTATE_START;
                } elseif ($char === '{') {
                    $this->tokenString .= '$';
                    yield $this->createToken(Token::TYPE_DOLLAR);
                    $this->tokenString .= $char;
                    $this->tokenFirstPosition = $position;
                    yield $this->createToken(Token::TYPE_START_EXPR);
                    $this->substate = self::SUBSTATE_START;
                } elseif ($char === '|') {
                    $this->tokenString .= '$';
                    yield $this->createToken(Token::TYPE_DOLLAR);
                    $this->tokenString .= $char;
                    $this->tokenFirstPosition = $position;
                    yield $this->createToken(Token::DELIMETER);
                } elseif ($this->isIdentifierChar($char, true)) {
                    $this->tokenString .= $char;
                    $this->substate = self::SUBSTATE_VAR;
                } else {
                    $this->tokenString .= '$';
                    yield $this->createToken(Token::TYPE_DOLLAR);
                    $this->tokenString .= $char;
                    $this->substate = self::SUBSTATE_DATA;
                }
                break;

            case self::SUBSTATE_VAR:
                if ($char === null) {
                    yield $this->createToken(Token::TYPE_VAR);
                } elseif ($this->isIdentifierChar($char, false)) {
                    $this->tokenString .= $char;
                } else {
                    yield $this->createToken(Token::TYPE_VAR);
                    $this->tokenFirstPosition = $position;
                    if ($char === '$') {
                        $this->substate = self::SUBSTATE_DOLLAR;
                    } elseif ($char === '{') {
                        $this->tokenString .= $char;
                        yield $this->createToken(Token::START_EXPR);
                        $this->substate = self::SUBSTATE_START;
                    } elseif ($char === '|') {
                        $this->tokenString .= $char;
                        yield $this->createToken(Token::DELIMETER);
                        $this->substate = self::SUBSTATE_START;
                    } else {
                        $this->substate = self::SUBSTATE_DATA;
                        $this->tokenString .= $char;
                    }
                }
                break;
        }
    }

    private function isIdentifierChar(string $char, bool $first): bool
    {
        if ($first) {
            return preg_match('/^[_a-zA-Z]$/', $char);
        } else {
            return preg_match('/^[_a-zA-Z0-9]$/', $char);
        }
    }
}
