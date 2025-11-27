<?php

namespace Concrete\Connection;

interface DriverInterface
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
    public function connect(string $host, string $db, string $user, string $pass): \PDO;

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
    public function compileSelect(string $table, array $columns, array $wheres, array $orders, array $joins, ?int $limit = null, ?int $offset = null): string;

    /**
     * Compile an update query.
     *
     * @param string $table
     * @param array $values
     * @param array $wheres
     * @return string
     */
    public function compileUpdate(string $table, array $values, array $wheres): string;

    /**
     * Compile a delete query.
     *
     * @param string $table
     * @param array $wheres
     * @return string
     */
    public function compileDelete(string $table, array $wheres): string;
}
