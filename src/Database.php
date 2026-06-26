<?php

namespace Concrete;

use Concrete\Connection\DriverInterface;

class Database
{
    private static ?\PDO $pdo = null;
    private static ?DriverInterface $driver = null;

    /**
     * Initialize the database connection.
     *
     * @param DriverInterface $driver
     * @param string $host
     * @param ?string $db
     * @param ?string $user
     * @param ?string $pass
     * @return void
     */
    public static function init(DriverInterface $driver, $host, $db, $user, $pass)
    {
        self::$driver = $driver;
        self::$pdo = $driver->connect($host, $db, $user, $pass);
    }

    /**
     * Initialize the database with an existing PDO connection.
     *
     * @param \PDO $pdo
     * @param DriverInterface $driver
     * @return void
     */
    public static function initFromPDO(\PDO $pdo, DriverInterface $driver)
    {
        self::$pdo = $pdo;
        self::$driver = $driver;
    }

    /**
     * Get the PDO connection instance.
     *
     * @return \PDO
     */
    public static function getConnection(): \PDO
    {
        if (self::$pdo === null) {
            throw new \RuntimeException('Database not initialized. Call Database::init() first.');
        }
        return self::$pdo;
    }

    /**
     * Get the database driver instance.
     *
     * @return DriverInterface
     */
    public static function getDriver(): DriverInterface
    {
        if (self::$driver === null) {
            throw new \RuntimeException('Database not initialized. Call Database::init() first.');
        }
        return self::$driver;
    }
}
