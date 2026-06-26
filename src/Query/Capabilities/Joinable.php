<?php

namespace Concrete\Query\Capabilities;

trait Joinable
{
    protected array $joins = [];

    /**
     * Add a join clause to the query.
     *
     * @param string $table
     * @param string $first
     * @param string $operator
     * @param string $second
     * @param string $type
     * @return self
     */
    public function join(string $table, string $first, string $operator, string $second, string $type = 'INNER'): self
    {
        $type = strtoupper(trim($type));
        $allowedTypes = ['INNER', 'LEFT', 'RIGHT', 'CROSS', 'FULL', 'FULL OUTER'];
        if (!in_array($type, $allowedTypes, true)) {
            throw new \InvalidArgumentException("Invalid join type: '$type'");
        }
        $allowedOperators = ['=', '!=', '<>', '<', '>', '<=', '>='];
        $operator = strtoupper(trim($operator));
        if (!in_array($operator, $allowedOperators, true)) {
            throw new \InvalidArgumentException("Invalid join operator: '$operator'");
        }
        $identifierPattern = '/^[a-zA-Z_][a-zA-Z0-9_]*(\.[a-zA-Z_][a-zA-Z0-9_]*)?$/';
        foreach ([$table, $first, $second] as $identifier) {
            if (!preg_match($identifierPattern, $identifier)) {
                throw new \InvalidArgumentException("Invalid SQL identifier: '$identifier'");
            }
        }
        $this->joins[] = "$type JOIN $table ON $first $operator $second";
        return $this;
    }

    /**
     * Add a left join to the query.
     *
     * @param string $table
     * @param string $first
     * @param string $operator
     * @param string $second
     * @return self
     */
    public function leftJoin(string $table, string $first, string $operator, string $second): self
    {
        return $this->join($table, $first, $operator, $second, 'LEFT');
    }

    /**
     * Add a right join to the query.
     *
     * @param string $table
     * @param string $first
     * @param string $operator
     * @param string $second
     * @return self
     */
    public function rightJoin(string $table, string $first, string $operator, string $second): self
    {
        return $this->join($table, $first, $operator, $second, 'RIGHT');
    }
}
