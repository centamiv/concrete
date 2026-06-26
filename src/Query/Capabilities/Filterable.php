<?php

namespace Concrete\Query\Capabilities;

trait Filterable
{
    protected array $wheres = [];
    protected array $params = [];

    private static int $sqCount = 0;

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

    /**
     * Add a column-to-column comparison (no parameterization — both sides are identifiers).
     * Use this for correlated subquery conditions such as "orders.user_id = users.id".
     *
     * @param string $col1
     * @param string $operator
     * @param string $col2
     * @return $this
     */
    public function whereColumn(string $col1, string $operator, string $col2): self
    {
        $identRe = '/^[a-zA-Z_][a-zA-Z0-9_]*(\.[a-zA-Z_][a-zA-Z0-9_]*)?$/';
        if (!preg_match($identRe, $col1)) {
            throw new \InvalidArgumentException("Invalid column identifier: '$col1'");
        }
        if (!preg_match($identRe, $col2)) {
            throw new \InvalidArgumentException("Invalid column identifier: '$col2'");
        }
        $operator = strtoupper(trim($operator));
        $allowed = ['=', '!=', '<>', '<', '>', '<=', '>='];
        if (!in_array($operator, $allowed, true)) {
            throw new \InvalidArgumentException("Invalid operator: '$operator'");
        }
        $this->wheres[] = "$col1 $operator $col2";
        return $this;
    }

    /**
     * Add a WHERE col IN (…) clause.
     * $values may be a plain array (each item is a bound parameter) or a Builder subquery.
     *
     * @param string $col
     * @param array|\Concrete\Query\Builder $values
     * @return $this
     */
    public function whereIn(string $col, $values): self
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*(\.[a-zA-Z_][a-zA-Z0-9_]*)?$/', $col)) {
            throw new \InvalidArgumentException("Invalid column identifier: '$col'");
        }
        if ($values instanceof \Concrete\Query\Builder) {
            $this->wheres[] = "$col IN (" . $this->embedSub($values) . ")";
            return $this;
        }
        if (!is_array($values)) {
            throw new \InvalidArgumentException('whereIn values must be an array or Builder instance');
        }
        if (empty($values)) {
            $this->wheres[] = '1 = 0';
            return $this;
        }
        $base = str_replace('.', '_', $col) . '_in' . count($this->params);
        $placeholders = [];
        foreach (array_values($values) as $i => $val) {
            $key = $base . '_' . $i;
            $this->params[$key] = $val;
            $placeholders[] = ':' . $key;
        }
        $this->wheres[] = "$col IN (" . implode(', ', $placeholders) . ")";
        return $this;
    }

    /**
     * Add a WHERE col NOT IN (…) clause.
     * $values may be a plain array or a Builder subquery.
     *
     * @param string $col
     * @param array|\Concrete\Query\Builder $values
     * @return $this
     */
    public function whereNotIn(string $col, $values): self
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*(\.[a-zA-Z_][a-zA-Z0-9_]*)?$/', $col)) {
            throw new \InvalidArgumentException("Invalid column identifier: '$col'");
        }
        if ($values instanceof \Concrete\Query\Builder) {
            $this->wheres[] = "$col NOT IN (" . $this->embedSub($values) . ")";
            return $this;
        }
        if (!is_array($values)) {
            throw new \InvalidArgumentException('whereNotIn values must be an array or Builder instance');
        }
        if (empty($values)) {
            $this->wheres[] = '1 = 1';
            return $this;
        }
        $base = str_replace('.', '_', $col) . '_nin' . count($this->params);
        $placeholders = [];
        foreach (array_values($values) as $i => $val) {
            $key = $base . '_' . $i;
            $this->params[$key] = $val;
            $placeholders[] = ':' . $key;
        }
        $this->wheres[] = "$col NOT IN (" . implode(', ', $placeholders) . ")";
        return $this;
    }

    /**
     * Add a WHERE EXISTS (subquery) clause.
     *
     * @param \Concrete\Query\Builder $sub
     * @return $this
     */
    public function whereExists(\Concrete\Query\Builder $sub): self
    {
        $this->wheres[] = "EXISTS (" . $this->embedSub($sub) . ")";
        return $this;
    }

    /**
     * Add a WHERE NOT EXISTS (subquery) clause.
     *
     * @param \Concrete\Query\Builder $sub
     * @return $this
     */
    public function whereNotExists(\Concrete\Query\Builder $sub): self
    {
        $this->wheres[] = "NOT EXISTS (" . $this->embedSub($sub) . ")";
        return $this;
    }

    /**
     * Compile a subquery into SQL and remap its param names to avoid collisions,
     * merging the remapped params into $this->params.
     */
    private function embedSub(\Concrete\Query\Builder $sub): string
    {
        $prefix = 'sq' . (++self::$sqCount) . '_';
        $sql = $sub->sql();
        foreach ($sub->getParams() as $key => $val) {
            $newKey = $prefix . $key;
            $sql = preg_replace('/:' . preg_quote($key, '/') . '\b/', ':' . $newKey, $sql);
            $this->params[$newKey] = $val;
        }
        return $sql;
    }
}
