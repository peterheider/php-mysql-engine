<?php
namespace Vimeo\MysqlEngine\Parser;

use Vimeo\MysqlEngine\JoinType;
use Vimeo\MysqlEngine\Query\WithClause;
use Vimeo\MysqlEngine\TokenType;
use Vimeo\MysqlEngine\Query\Expression\BinaryOperatorExpression;
use Vimeo\MysqlEngine\Query\Expression\ColumnExpression;
use Vimeo\MysqlEngine\Query\Expression\Expression;
use Vimeo\MysqlEngine\Query\Expression\StubExpression;
use Vimeo\MysqlEngine\Query\Expression\SubqueryExpression;
use Vimeo\MysqlEngine\Query\FromClause;

final class WithParser
{
    /**
     * @var int
     */
    private $pointer;

    /**
     * @var array<int, Token>
     */
    private $tokens;

    /**
     * @param array<int, Token> $tokens
     */
    public function __construct(int $pointer, array $tokens)
    {
        $this->pointer = $pointer;
        $this->tokens = $tokens;
    }

    /**
     * @return array{0:int, 1:WithClause}
     */
    public function parse()
    {
        if ($this->tokens[$this->pointer]->value !== 'WITH') {
            throw new ParserException("Parser error: expected WITH");
        }
        $with = new WithClause();
        $this->pointer++;
        $count = \count($this->tokens);

        $token = $this->tokens[$this->pointer];

        if (TokenType::RESERVED === $token->type) {
            if ('RECURSIVE' === $token->value) {
                $with->recursive = true;
                $this->pointer++;
            } else {
                throw new ParserException("Parser error: expected RECURSIVE");
            }
        }

        while ($this->pointer < $count) {
            $token = $this->tokens[$this->pointer];

            switch ($token->type) {
                case TokenType::IDENTIFIER:
                    $alias = $token->value;
                    $this->pointer++;
                    $next = $this->tokens[$this->pointer] ?? null;

                    if ($next === null && $next->value !== 'AS') {
                        throw new ParserException("Expected keyword after identifier");
                    }

                    $this->pointer++;
                    $next = $this->tokens[$this->pointer] ?? null;

                    if ($next === null || $next->type !== TokenType::PAREN) {
                        throw new ParserException("Expected query after identifier");
                    }

                    $subquery = $this->getSubquery($alias);
                    $with->addTable($subquery);

                    break;

                case TokenType::SEPARATOR:
                    if ($token->value !== ',') {
                        throw new ParserException("Unexpected {$token->value}");
                    }

                    break;

                case TokenType::CLAUSE:
                    if ('SELECT' !== $token->value) {
                        throw new ParserException("Expected SELECT");
                    }
                    return [$this->pointer - 1, $with];

                default:
                    throw new ParserException("Unexpected {$token->value}");
            }

            $this->pointer++;
        }

        return [$this->pointer, $with];
    }

    /**
     * @return array{
     *         name: string,
     *         subquery: SubqueryExpression,
     *         join_type: JoinType::*,
     *         alias: string
     *  }
     */
    private function getSubquery(string $alias)
    {
        $close = SQLParser::findMatchingParen($this->pointer, $this->tokens);

        $subquery_tokens = \array_slice(
            $this->tokens,
            $this->pointer + 1,
            $close - $this->pointer - 1
        );
        if (!\count($subquery_tokens)) {
            throw new ParserException("Empty parentheses found");
        }
        $this->pointer = $close;

        $subquery_sql = \implode(
            ' ',
            \array_map(
                function ($token) {
                    return $token->value;
                },
                $subquery_tokens
            )
        );
        $parser = new SelectParser(0, $subquery_tokens, $subquery_sql);
        $select = $parser->parse();
        $expr = new SubqueryExpression($select, '');

        return [
            'name' => $alias,
            'subquery' => $expr,
            'join_type' => JoinType::JOIN,
            'alias' => $alias
        ];
    }
}
