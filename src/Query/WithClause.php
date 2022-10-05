<?php
namespace Vimeo\MysqlEngine\Query;

use Vimeo\MysqlEngine\JoinType;
use Vimeo\MysqlEngine\Query\Expression\SubqueryExpression;

final class WithClause
{
    /**
     * @var array<
     *      int,
     *      array{
     *          name:string,
     *          subquery:SubqueryExpression,
     *          join_type:JoinType::*,
     *          join_operator:'ON'|'USING',
     *          alias:string,
     *          join_expression:null|Expression
     *      }
     *  >
     */
    public $tables = [];

    /**
     * @var bool
     */
    public $recursive = false;

    /**
     * @param array{
     *        name:string,
     *        subquery:SubqueryExpression,
     *        join_type:JoinType::*,
     *        join_operator:'ON'|'USING',
     *        alias:string,
     *        join_expression:null|Expression
     * } $table
     *
     * @return void
     */
    public function addTable(array $table)
    {
        $this->tables[] = $table;
    }
}
