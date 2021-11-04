<?php

namespace Sterzik\Ut;

use Exception;

class TokenizerState
{
    const STATE_NORMAL = "normal";
    const STATE_EXPR = "expr";

    const SUBSTATE_START = "start";
    const SUBSTATE_DATA = "data";
    const SUBSTATE_DOLLAR = "dollar";
    const SUBSTATE_VAR = "var";
    const SUBSTATE_WHITE = "white";
    const SUBSTATE_IDENTIFIER = "identifier";
    const SUBSTATE_DOT = "dot";
    const SUBSTATE_NUMBER = "number";
    const SUBSTATE_NUMBER_DEC = "number_dec";
    const SUBSTATE_NUMBER_EXP = "number_exp";
    const SUBSTATE_NUMBER_EXP2 = "number_exp2";
    const SUBSTATE_NUMBER_EXP3 = "number_exp3";
    const SUBSTATE_OPERATOR = "operator";
    const SUBSTATE_DQSTRING = "dqstring";
    const SUBSTATE_DQSTRING_ESC = "dqstring_esc";
    const SUBSTATE_SQSTRING = "sqstring";
    const SUBSTATE_SQSTRING_ESC = "sqstring_esc";

    const OPERATORS = [
        "+", "-", "*", "/", "&", "&&", "|", "||", ",", ".", "!",
        '[', ']', '(', ')', '{', '}',
        '>', '<', '>=', '<=',
        '++', '--',
    ];

    /** @var string */
    private $state;

    /** @var string */
    private $tokenString;

    /** @var array<int> */
    private $tokenFirstPosition;

    /** @var array|null */
    private $operatorsByLength = null;

    public function __construct()
    {
        $this->state = self::STATE_NORMAL;
        $this->substate = self::SUBSTATE_START;
        $this->tokenString = "";
        $this->tokenFirstPosition = [0, 0];
        $this->operatorsByLength = null;
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
            return $this->putCharInvalid($char, $position);
        }
    }

    private function createToken(string $type, ?string $data = null): Token
    {
        $stateChangeCallback = function($state) {
            if ($this->substate !== self::SUBSTATE_START || $this->tokenString !== '') {
                throw new Exception(sprintf("Invalid tokenizer state change to: %s", $state));
            }
            $this->state = $state;
        };

        $token = new Token($stateChangeCallback, $type, $data ?? $this->tokenString, $this->tokenFirstPosition);

        if ($data === null) {
            $this->tokenString = "";
        }

        return $token;
    }

    private function getOperatorsByLength(): array
    {
        if ($this->operatorsByLength === null) {
            $this->operatorsByLength = self::OPERATORS;
            usort($this->operatorsByLength, function ($a, $b){return strlen($b) - strlen($a);});
        }
        return $this->operatorsByLength;
    }

    private function isPartOfOperator(string $protoOperator): bool
    {
        $length = strlen($protoOperator);

        foreach ($this->getOperatorsByLength() as $operator) {
            if (strlen($operator) >= $length && substr($operator, 0, $length) === $protoOperator) {
                return true;
            }
        }

        return false;
    }

    private function putCharInvalid(?string $char, array $position): iterable
    {
        $this->tokenFirstPosition = $position;
        $this->substate = self::SUBSTATE_START;
        if ($char !== null) {
            $this->tokenString .= $char;
            yield $this->createToken(Token::TYPE_INVALID);
        } else {
            yield $this->createToken(Token::TYPE_EOF);
        }
    }

    private function putCharExpr(?string $char, array $position): iterable
    {
        $restart = true;
        while($restart) {
            $restart = false;
            switch ($this->substate) {
                case self::SUBSTATE_START:
                    $this->tokenFirstPosition = $position;
                    if ($char === null) {
                        yield $this->createToken(Token::TYPE_EOF);
                    } elseif ($this->isWhiteChar($char)) {
                        $this->substate = self::SUBSTATE_WHITE;
                        $this->tokenString .= $char;
                    } elseif ($char === '$') {
                        $this->substate = self::SUBSTATE_DOLLAR;
                        $this->tokenString .= $char;
                    } elseif ($char === "}" || $char === '{') {
                        //handle this operators separately, because they are able to switch the state
                        $this->tokenString .= $char;
                        yield $this->createToken(Token::TYPE_OPERATOR);
                    } elseif ($char === '"') {
                        $this->substate = self::SUBSTATE_DQSTRING;
                    } elseif ($char === "'") {
                        $this->substate = self::SUBSTATE_SQSTRING;
                    } elseif ($this->isIdentifierChar($char, true)) {
                        $this->substate = self::SUBSTATE_IDENTIFIER;
                        $this->tokenString .= $char;
                    } elseif ($this->isDigitChar($char)) {
                        $this->tokenString .= $char;
                        $this->substate = self::SUBSTATE_NUMBER;
                    } elseif ($char === '.') {
                        $this->tokenString .= $char;
                        $this->substate = self::SUBSTATE_DOT;
                    } elseif ($this->isOperatorChar($char)) {
                        $this->substate = self::SUBSTATE_OPERATOR;
                        $this->tokenString .= $char;
                    } else {
                        $this->tokenString .= $char;
                        yield $this->createToken(Token::TYPE_INVALID);
                    }
                    break;

                case self::SUBSTATE_WHITE:
                    if ($char !== null && $this->isWhiteChar($char)) {
                        $this->tokenString .= $char;
                    } else {
                        yield $this->createToken(Token::TYPE_WHITE);
                        $this->substate = self::SUBSTATE_START;
                        $restart = true;
                    }
                    break;

                case self::SUBSTATE_DOLLAR:
                    if($char !== null && $this->isIdentifierChar($char, true)) {
                        $this->tokenString .= $char;
                        $this->substate = self::SUBSTATE_VAR;
                    } else {
                        yield $this->createToken(Token::TYPE_OPERATOR);
                        $this->substate = self::SUBSTATE_START;
                        $restart = true;
                    }
                    break;

                case self::SUBSTATE_VAR:
                    if ($char !== null && $this->isIdentifierChar($char, false)) {
                        $this->tokenString .= $char;
                    } else {
                        yield $this->createToken(Token::TYPE_VAR);
                        $this->substate = self::SUBSTATE_START;
                        $restart = true;
                    }
                    break;
                
                case self::SUBSTATE_OPERATOR:
                    if ($char !== null && $this->isOperatorChar($char) && $char !== '{' && $char !== '}') {
                        $this->tokenString .= $char;
                        if (!$this->isPartOfOperator($this->tokenString)) {
                            $newOpLength = strlen($this->tokenString);
                            $found = false;
                            foreach ($this->getOperatorsByLength() as $op) {
                                $opLength = strlen($op);
                                if ($opLength <= $newOpLength && substr($this->tokenString, 0, $opLength) === $op) {
                                    yield $this->createToken(Token::TYPE_OPERATOR, $op);
                                    $this->tokenString = substr($this->tokenString, $opLength);
                                    $found = true;
                                    break;
                                }
                            }
                            if (!$found) {
                                yield $this->createToken(Token::TYPE_INVALID, substr($this->tokenString, 0, 1));
                                $this->tokenString = substr($this->tokenString, 0, 1);
                                if ($this->tokenString === '') {
                                    $this->substate = self::SUBSTATE_START;
                                }

                            }
                        }
                    } else {
                        $found = false;
                        $newOpLength = strlen($this->tokenString);
                        foreach ($this->getOperatorsByLength() as $op) {
                            $opLength = strlen($op);
                            if ($opLength <= $newOpLength && substr($this->tokenString, 0, $opLength) === $op) {
                                yield $this->createToken(Token::TYPE_OPERATOR, $op);
                                $this->tokenString = substr($this->tokenString, $opLength);
                                if ($this->tokenString === '') {
                                    $this->substate = self::SUBSTATE_START;
                                }
                                $found = true;
                                $restart = true;
                                break;
                            }
                        }
                        if (!$found) {
                            yield $this->createToken(Token::TYPE_INVALID, substr($this->tokenString, 0, 1));
                            $this->tokenString = substr($this->tokenString, 0, 1);
                            if ($this->tokenString === '') {
                                $this->substate = self::SUBSTATE_START;
                            }
                            $restart = true;
                        }
                    }
                    break;
                    
                case self::SUBSTATE_DQSTRING:
                    if ($char === '\\') {
                        $this->tokenString .= $char;
                        $this->substate = self::SUBSTATE_DQSTRING_ESC;
                    } elseif ($char === '"') {
                        yield $this->createToken(self::TYPE_STRING);
                        $this->substate = self::SUBSTATE_START;
                    } else {
                        $this->tokenString .= $char;
                    }
                    break;
                    
                case self::SUBSTATE_SQSTRING:
                    if ($char === '\\') {
                        $this->tokenString .= $char;
                        $this->substate = self::SUBSTATE_SQSTRING_ESC;
                    } elseif ($char === "'") {
                        yield $this->createToken(self::TYPE_STRING);
                        $this->substate = self::SUBSTATE_START;
                    } else {
                        $this->tokenString .= $char;
                    }
                    break;

                case self::SUBSTATE_IDENTIFIER:
                    if ($char !== null && $this->isIdentifierChar($char, false)) {
                        $this->tokenString .= $char;
                    } else {
                        yield $this->createToken(Token::TYPE_IDENTIFIER);
                        $this->substate = self::SUBSTATE_START;
                        $restart = true;
                    }
                    break;

                case self::SUBSTATE_DOT:
                    if ($char !== null && $this->isDigitChar($char)) {
                        $this->tokenString .= $char;
                        $this->substate = self::SUBSTATE_NUMBER_DEC;
                    } else {
                        yield $this->createToken(
                            in_array($this->tokenString, self::OPERATORS) ? Token::TYPE_OPERATOR : Token::TYPE_INVALID
                        );
                        $this->substate = self::SUBSTATE_START;
                        $restart = true;
                    }
                    break;
                
                case self::SUBSTATE_NUMBER:
                    if ($char !== null && $this->isDigitChar($char)) {
                        $this->tokenString .= $char;
                    } elseif ($char === '.') {
                        $this->tokenString .= $char;
                        $this->substate = self::SUBSTATE_NUMBER_DEC;
                    } elseif ($char === 'e' || $char === 'E') {
                        $this->tokenString .= $char;
                        $this->substate = self::SUBSTATE_NUMBER_EXP;
                    } else {
                        yield $this->createToken(Token::TYPE_INTEGER);
                        $this->substate = self::SUBSTATE_START;
                        $restart = true;
                    }
                    break;

                case self::SUBSTATE_NUMBER_DEC:
                    if ($char !== null && $this->isDigitChar($char)) {
                        $this->tokenString .= $char;
                    } elseif ($char === '.') {
                        yield $this->createToken(Token::TYPE_FLOAT);
                        $this->tokenString .= $char;
                        yield $this->createToken(Token::TYPE_INVALID);
                        $this->substate = self::SUBSTATE_START;
                    } elseif ($char === 'e' || $char === 'E') {
                        $this->tokenString .= $char;
                        $this->substate = self::SUBSTATE_NUMBER_EXP;
                    } else {
                        yield $this->createToken(Token::TYPE_FLOAT);
                        $this->substate = self::SUBSTATE_START;
                        $restart = true;
                    }
                    break;

                case self::SUBSTATE_NUMBER_EXP:
                    if ($char !== null && $this->isDigitChar($char)) {
                        $this->tokenString .= $char;
                        $this->substate = self::SUBSTATE_NUMBER_EXP3;
                    } elseif ($char === '.' || $char === 'e' || $char === 'E') {
                        $this->tokenString .= $char;
                        yield $this->createToken(Token::TYPE_INVALID);
                        $this->substate = self::SUBSTATE_START;
                    } elseif ($char === '+' || $char === '-') {
                        $this->tokenString .= $char;
                        $this->substate = self::SUBSTATE_NUMBER_EXP2;
                    } else {
                        yield $this->createToken(Token::TYPE_FLOAT);
                        $this->substate = self::SUBSTATE_START;
                        $restart = true;
                    }
                    break;

                case self::SUBSTATE_NUMBER_EXP2:
                    if ($char !== null && $this->isDigitChar($char)) {
                        $this->tokenString .= $char;
                        $this->substate = self::SUBSTATE_NUMBER_EXP3;
                    } else {
                        $this->tokenString .= $char;
                        yield $this->createToken(Token::TYPE_INVALID);
                        $this->substate = self::SUBSTATE_START;
                    }
                    break;

                case self::SUBSTATE_NUMBER_EXP3:
                    if ($char !== null && $this->isDigitChar($char)) {
                        $this->tokenString .= $char;
                    } else {
                        yield $this->createToken(Token::TYPE_FLOAT);
                        $restart = true;
                        $this->substate = self::SUBSTATE_START;
                    }
                    break;

                default:
                    $this->tokenString .= $char;
                    yield $this->createToken(Token::TYPE_INVALID);
                    break;
                    
            }
        }
    }

    private function putCharNormal(?string $char, array $position): iterable
    {
        $restart = true;
        while($restart) {
            $restart = false;
            switch ($this->substate) {
                case self::SUBSTATE_START:
                    $this->tokenFirstPosition = $position;
                    if ($char === null) {
                        yield $this->createToken(Token::TYPE_EOF);
                    } elseif ($char === '$') {
                        $this->substate = self::SUBSTATE_DOLLAR;
                        $this->tokenString .= $char;
                    } elseif ($char === '{') {
                        $this->tokenString .= $char;
                        yield $this->createToken(Token::TYPE_START_EXPR);
                    } elseif ($char === '|') {
                        $this->tokenString .= $char;
                        yield $this->createToken(Token::TYPE_DELIMETER);
                    } else {
                        $this->substate = self::SUBSTATE_DATA;
                        $this->tokenString .= $char;
                    }
                    break;

                case self::SUBSTATE_DATA:
                    if ($char === '$' || $char === '{' || $char === '|') {
                        yield $this->createToken(Token::TYPE_DATA);
                        $this->substate = self::SUBSTATE_START;
                        $restart = true;
                    } else {
                        $this->tokenString .= $char;
                    }
                    break;

                case self::SUBSTATE_DOLLAR:
                    if ($char === '$') {
                        $this->tokenString .= $char;
                        yield $this->createToken(Token::TYPE_DOLLAR);
                        $this->substate = self::SUBSTATE_START;
                    } elseif($char !== null && $this->isIdentifierChar($char, true)) {
                        $this->tokenString .= $char;
                        $this->substate = self::SUBSTATE_VAR;
                    } else {
                        yield $this->createToken(Token::TYPE_DOLLAR);
                        $this->substate = self::SUBSTATE_START;
                        $restart = true;
                    }
                    break;

                case self::SUBSTATE_VAR:
                    if ($char !== null && $this->isIdentifierChar($char, false)) {
                        $this->tokenString .= $char;
                    } else {
                        yield $this->createToken(Token::TYPE_VAR);
                        $this->substate = self::SUBSTATE_START;
                        $restart = true;
                    }
                    break;

                default:
                    $this->tokenString .= $char;
                    yield $this->createToken(Token::TYPE_INVALID);
                    break;
            }
        }
    }

    private function isIdentifierChar(string $char, bool $first): bool
    {
        return preg_match($first ? '/^[_a-zA-Z]$/' : '/^[_a-zA-Z0-9]$/', $char);
    }

    private function isWhiteChar(string $char): bool
    {
        return preg_match('/^\s$/', $char);
    }

    private function isOperatorChar(string $char): bool
    {
        return (strpos(implode("", self::OPERATORS), $char) !== false);
    }

    private function isDigitChar(string $char): bool
    {
        return preg_match('/^[0-9]$/', $char);
    }
}
