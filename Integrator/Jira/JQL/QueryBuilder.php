<?php
/**
 * Created by PhpStorm.
 * User: Vinícius
 * Date: 03/03/17
 * Time: 15:23
 */

namespace Integrator\Jira\JQL;

/**
 * Class Builder
 * Build JQL query strings
 */
class QueryBuilder
{
    /**
     * @var string
     */
    private $jqlString = '';
    
    /**
     * @return string
     */
    public function getJqlString()
    {
        return $this->jqlString;
    }
    
    /**
     * @param string $clause
     *
     * @return $this
     */
    public function where($clause)
    {
        return $this->addClause($clause, '');
    }
    
    /**
     * @param string $clause
     * @param string $operator AND|OR
     *
     * @return $this
     */
    private function addClause($clause, $operator)
    {
        $prefix = '';
        if ($operator != '') {
            $prefix = " $operator ";
        }
        
        if ($clause != '') {
            $this->jqlString .= $prefix . $clause;
        }
        
        return $this;
    }
    
    /**
     * @param string $clause
     *
     * @return $this
     */
    public function addAnd($clause)
    {
        return $this->addClause($clause, 'AND');
    }
}
