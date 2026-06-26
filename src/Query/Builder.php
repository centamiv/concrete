<?php

namespace Concrete\Query;

use Concrete\Database;
use Concrete\Query\Capabilities\Filterable;
use Concrete\Query\Capabilities\Orderable;
use Concrete\Query\Capabilities\Joinable;

class Builder
{
    use Filterable;
    use Orderable;
    use Joinable;

    protected string $table;
    protected string $modelClass;
    protected array $columns = ['*'];
    protected ?int $limit = null;
    protected ?int $offset = null;
    protected array $unions = [];

    private static int $unionCount = 0;

    /**
     * Set the table and model class for the query.
     *
     * @param string $modelClass
     * @return $this
     */
    public function table(string $modelClass): self
    {
        $this->modelClass = $modelClass;
        $this->table = $modelClass::TABLE;
        return $this;
    }

    /**
     * Set the columns to be selected.
     * Accepts plain column names, When, or Subquery instances.
     *
     * @param string|When|Subquery ...$cols
     * @return $this
     */
    public function select(...$cols): self
    {
        $this->columns = [];
        foreach ($cols as $col) {
            if ($col instanceof When || $col instanceof Subquery) {
                $this->columns[] = $col;
                continue;
            }
            if (strpos($col, '.') === false && $col !== '*') {
                $this->columns[] = $this->table . '.' . $col;
            } else {
                $this->columns[] = $col;
            }
        }
        return $this;
    }

    /**
     * Set the limit of results.
     *
     * @param int $limit
     * @return $this
     */
    public function take(int $limit): self
    {
        $this->limit = $limit;
        return $this;
    }

    /**
     * Set the offset of results.
     *
     * @param int $offset
     * @return $this
     */
    public function skip(int $offset): self
    {
        $this->offset = $offset;
        return $this;
    }

    /**
     * Add a UNION clause (distinct rows).
     *
     * @param Builder $query
     * @return $this
     */
    public function union(Builder $query): self
    {
        $this->unions[] = ['builder' => $query, 'all' => false];
        return $this;
    }

    /**
     * Add a UNION ALL clause (all rows, including duplicates).
     *
     * @param Builder $query
     * @return $this
     */
    public function unionAll(Builder $query): self
    {
        $this->unions[] = ['builder' => $query, 'all' => true];
        return $this;
    }

    /**
     * Return the PDO parameters currently bound to this query.
     *
     * @return array
     */
    public function getParams(): array
    {
        return $this->params;
    }

    /**
     * Get the generated SQL string.
     *
     * @return string
     */
    public function sql(): string
    {
        $driver = Database::getDriver();

        // Resolve any column expressions and collect their params.
        $columns = [];
        foreach ($this->columns as $col) {
            if ($col instanceof When || $col instanceof Subquery) {
                foreach ($col->getParams() as $key => $val) {
                    $this->params[$key] = $val;
                }
                $columns[] = $col->toSql();
            } else {
                $columns[] = $col;
            }
        }

        if (empty($this->unions)) {
            return $driver->compileSelect(
                $this->table,
                $columns,
                $this->wheres,
                $this->orders,
                $this->joins,
                $this->limit,
                $this->offset
            );
        }

        // UNION: compile main part without ORDER BY / LIMIT / OFFSET —
        // those are applied to the whole result at the end.
        $sql = '(' . $driver->compileSelect(
            $this->table, $columns, $this->wheres, [], $this->joins, null, null
        ) . ')';

        foreach ($this->unions as $union) {
            $sub = $union['builder'];
            $subSql = $sub->sql();
            $subParams = $sub->getParams();

            self::$unionCount++;
            $prefix = 'u' . self::$unionCount . '_';

            foreach ($subParams as $key => $val) {
                $newKey = $prefix . $key;
                $subSql = preg_replace('/:' . preg_quote($key, '/') . '\b/', ':' . $newKey, $subSql);
                $this->params[$newKey] = $val;
            }

            $keyword = $union['all'] ? 'UNION ALL' : 'UNION';
            $sql .= " $keyword ($subSql)";
        }

        if (!empty($this->orders)) {
            $sql .= ' ORDER BY ' . implode(', ', $this->orders);
        }

        $limit  = $this->limit;
        $offset = $this->offset;
        if ($offset !== null && $limit === null) {
            $limit = PHP_INT_MAX;
        }
        if ($limit !== null) {
            $sql .= ' LIMIT ' . $limit;
        }
        if ($offset !== null) {
            $sql .= ' OFFSET ' . $offset;
        }

        return $sql;
    }

    /**
     * Execute the query and get the results.
     *
     * @return array
     */
    public function get(): array
    {
        $sql = $this->sql();

        $stmt = Database::getConnection()->prepare($sql);
        $stmt->execute($this->params);

        $results = [];
        while ($row = $stmt->fetch()) {
            $obj = new $this->modelClass();
            $obj->hydrate($row);
            $results[] = $obj;
        }
        return $results;
    }

    /**
     * Execute the query and get the results as an array of associative arrays.
     *
     * @return array
     */
    public function getRows(): array
    {
        $sql = $this->sql();

        $stmt = Database::getConnection()->prepare($sql);
        $stmt->execute($this->params);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get the first result of the query.
     *
     * @return mixed|null
     */
    public function first()
    {
        return (clone $this)->take(1)->get()[0] ?? null;
    }

    /**
     * Get the first result of the query as an associative array.
     *
     * @return array|null
     */
    public function firstRow(): ?array
    {
        return (clone $this)->take(1)->getRows()[0] ?? null;
    }

    /**
     * Determine if any rows exist for the current query.
     *
     * @return bool
     */
    public function exists(): bool
    {
        return $this->firstRow() !== null;
    }

    /**
     * Retrieve the "count" result of the query.
     *
     * @return int
     */
    public function count(): int
    {
        // Clone the builder to avoid modifying the original query
        $builder = clone $this;

        $builder->columns = ['COUNT(*)'];

        $builder->limit = null;
        $builder->offset = null;
        $builder->orders = [];

        $sql = $builder->sql();

        $stmt = Database::getConnection()->prepare($sql);
        $stmt->execute($builder->params);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Update records in the database.
     *
     * @param array $values
     * @return int
     */
    public function update(array $values): int
    {
        $driver = Database::getDriver();
        $sql = $driver->compileUpdate($this->table, $values, $this->wheres);

        // Merge values to bind with existing where params
        $params = array_merge($values, $this->params);

        $stmt = Database::getConnection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount();
    }

    /**
     * Delete records from the database.
     *
     * @return int
     */
    public function delete(): int
    {
        $driver = Database::getDriver();
        $sql = $driver->compileDelete($this->table, $this->wheres);

        $stmt = Database::getConnection()->prepare($sql);
        $stmt->execute($this->params);

        return $stmt->rowCount();
    }
}
