<?php

namespace Concrete\Query\Capabilities;

trait Orderable
{
    protected array $orders = [];

    /**
     * Add an "order by" clause to the query.
     *
     * @param string $column
     * @param string $direction
     * @return self
     */
    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        // Basic validation for security
        $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        $this->orders[] = "$column $direction";
        return $this;
    }
}
