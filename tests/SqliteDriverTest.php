<?php
namespace Tests;

use Concrete\Connection\SqliteDriver;
use PHPUnit\Framework\TestCase;

class SqliteDriverTest extends TestCase
{
    public function testCompileSelect()
    {
        $driver = new SqliteDriver();
        $sql = $driver->compileSelect('users', ['id', 'name'], ['id = 1'], ['name ASC'], ['INNER JOIN roles ON users.role_id = roles.id']);
        $this->assertStringContainsString('SELECT id, name FROM users', $sql);
        $this->assertStringContainsString('INNER JOIN roles ON users.role_id = roles.id', $sql);
        $this->assertStringContainsString('WHERE id = 1', $sql);
        $this->assertStringContainsString('ORDER BY name ASC', $sql);
    }

    public function testCompileSelectWithLimit()
    {
        $driver = new SqliteDriver();
        $sql = $driver->compileSelect('users', ['*'], [], [], [], 10, null);
        $this->assertStringContainsString('SELECT * FROM users', $sql);
        $this->assertStringContainsString('LIMIT 10', $sql);
    }

    public function testCompileSelectWithOffset()
    {
        $driver = new SqliteDriver();
        $sql = $driver->compileSelect('users', ['*'], [], [], [], 10, 5);
        $this->assertStringContainsString('SELECT * FROM users', $sql);
        $this->assertStringContainsString('LIMIT 10', $sql);
        $this->assertStringContainsString('OFFSET 5', $sql);
    }

    public function testCompileUpdate()
    {
        $driver = new SqliteDriver();
        $sql = $driver->compileUpdate('users', ['name' => 'John', 'email' => 'john@example.com'], ['id = 1']);
        $this->assertStringContainsString('UPDATE users SET', $sql);
        $this->assertStringContainsString('name = :name', $sql);
        $this->assertStringContainsString('email = :email', $sql);
        $this->assertStringContainsString('WHERE id = 1', $sql);
    }

    public function testCompileDelete()
    {
        $driver = new SqliteDriver();
        $sql = $driver->compileDelete('users', ['id = 1']);
        $this->assertStringContainsString('DELETE FROM users', $sql);
        $this->assertStringContainsString('WHERE id = 1', $sql);
    }

    public function testConnect()
    {
        $driver = new SqliteDriver();
        // SQLite uses :memory: for in-memory database
        $pdo = $driver->connect('', ':memory:', '', '');
        $this->assertInstanceOf(\PDO::class, $pdo);
        $this->assertEquals(\PDO::ERRMODE_EXCEPTION, $pdo->getAttribute(\PDO::ATTR_ERRMODE));
    }
}
