<?php

namespace Concrete\Query\Capabilities;

trait Filterable
{
    protected array $wheres = [];
    protected array $params = [];

    /**
     * Add a basic where clause to the query.
     *
     * @param string $col
     * @param string $operator
     * @param mixed $val
     * @return $this
     */
    public function where(string $col, string $operator, $val): self
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*(\.[a-zA-Z_][a-zA-Z0-9_]*)?$/', $col)) {
            throw new \InvalidArgumentException("Invalid column identifier: '$col'");
        }
        $operator = strtoupper(trim($operator));
        $allowed = ['=', '!=', '<>', '<', '>', '<=', '>=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN', 'IS', 'IS NOT'];
        if (!in_array($operator, $allowed, true)) {
            throw new \InvalidArgumentException("Invalid SQL operator: '$operator'");
        }
        $paramName = str_replace('.', '_', $col) . count($this->params);
        $this->wheres[] = "$col $operator :$paramName";
        $this->params[$paramName] = $val;
        return $this;
    }
}
