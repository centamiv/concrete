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
     *
     * @param string ...$cols
     * @return $this
     */
    public function select(string ...$cols): self
    {
        // Map columns: if they don't contain a dot, add the current table
        $this->columns = array_map(function ($col) {
            if (strpos($col, '.') === false && $col !== '*') {
                return $this->table . '.' . $col;
            }
            return $col;
        }, $cols);

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
     * Get the generated SQL string.
     *
     * @return string
     */
    public function sql(): string
    {
        $driver = Database::getDriver();

        return $driver->compileSelect(
            $this->table,
            $this->columns,
            $this->wheres,
            $this->orders,
            $this->joins,
            $this->limit,
            $this->offset
        );
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
        return $this->take(1)->get()[0] ?? null;
    }

    /**
     * Get the first result of the query as an associative array.
     *
     * @return array|null
     */
    public function firstRow(): ?array
    {
        return $this->take(1)->getRows()[0] ?? null;
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
