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
        $paramName = str_replace('.', '_', $col) . count($this->params);
        $this->wheres[] = "$col $operator :$paramName";
        $this->params[$paramName] = $val;
        return $this;
    }
}
