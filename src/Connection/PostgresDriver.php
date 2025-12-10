<?php

namespace Concrete\Connection;

class PostgresDriver implements DriverInterface
{
    /**
     * Connect to the database.
     *
     * @param string $host
     * @param string $db
     * @param string $user
     * @param string $pass
     * @return \PDO
     */
    public function connect($host, $db, $user, $pass): \PDO
    {
        $dsn = "pgsql:host=$host;dbname=$db";
        return new \PDO($dsn, $user, $pass, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC
        ]);
    }

    /**
     * Compile the SELECT query.
     *
     * @param string $table
     * @param array $cols
     * @param array $wheres
     * @param array $orders
     * @param array $joins
     * @param int|null $limit
     * @param int|null $offset
     * @return string
     */
    public function compileSelect($table, $cols, $wheres, $orders, $joins, ?int $limit = null, ?int $offset = null): string
    {
        $sql = "SELECT " . implode(', ', $cols) . " FROM " . $table;

        if (!empty($joins)) {
            $sql .= " " . implode(' ', $joins);
        }

        if (!empty($wheres)) {
            $sql .= " WHERE " . implode(' AND ', $wheres);
        }

        if (!empty($orders)) {
            $sql .= " ORDER BY " . implode(', ', $orders);
        }

        if ($limit !== null) {
            $sql .= " LIMIT $limit";
        }

        if ($offset !== null) {
            $sql .= " OFFSET $offset";
        }

        return $sql;
    }

    /**
     * Compile an update query.
     *
     * @param string $table
     * @param array $values
     * @param array $wheres
     * @return string
     */
    public function compileUpdate(string $table, array $values, array $wheres): string
    {
        $sets = array_map(fn($col) => "$col = :$col", array_keys($values));
        $sql = "UPDATE $table SET " . implode(', ', $sets);

        if (!empty($wheres)) {
            $sql .= " WHERE " . implode(' AND ', $wheres);
        }

        return $sql;
    }

    /**
     * Compile a delete query.
     *
     * @param string $table
     * @param array $wheres
     * @return string
     */
    public function compileDelete(string $table, array $wheres): string
    {
        $sql = "DELETE FROM $table";

        if (!empty($wheres)) {
            $sql .= " WHERE " . implode(' AND ', $wheres);
        }

        return $sql;
    }
}
