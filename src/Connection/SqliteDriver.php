<?php

namespace Concrete\Connection;

class SqliteDriver implements DriverInterface
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
    public function connect(string $host, string $db, string $user, string $pass): \PDO
    {
        // For SQLite, $db is the file path, $host is ignored
        // $user and $pass are also ignored for file-based SQLite
        $dsn = "sqlite:$db";
        return new \PDO($dsn, null, null, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC
        ]);
    }

    /**
     * Compile the SELECT query.
     *
     * @param string $table
     * @param array $columns
     * @param array $wheres
     * @param array $orders
     * @param array $joins
     * @param int|null $limit
     * @param int|null $offset
     * @return string
     */
    public function compileSelect(string $table, array $columns, array $wheres, array $orders, array $joins, ?int $limit = null, ?int $offset = null): string
    {
        $sql = "SELECT " . implode(', ', $columns) . " FROM " . $table;

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
