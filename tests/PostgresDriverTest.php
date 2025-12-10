<?php
namespace Tests;

use Concrete\Connection\PostgresDriver;
use PHPUnit\Framework\TestCase;

class PostgresDriverTest extends TestCase
{
    public function testCompileSelect()
    {
        $driver = new PostgresDriver();
        $sql = $driver->compileSelect('users', ['id', 'name'], ['id = 1'], ['name ASC'], ['INNER JOIN roles ON users.role_id = roles.id']);
        $this->assertStringContainsString('SELECT id, name FROM users', $sql);
        $this->assertStringContainsString('INNER JOIN roles ON users.role_id = roles.id', $sql);
        $this->assertStringContainsString('WHERE id = 1', $sql);
        $this->assertStringContainsString('ORDER BY name ASC', $sql);
    }

    public function testCompileSelectWithLimit()
    {
        $driver = new PostgresDriver();
        $sql = $driver->compileSelect('users', ['*'], [], [], [], 10, null);
        $this->assertStringContainsString('SELECT * FROM users', $sql);
        $this->assertStringContainsString('LIMIT 10', $sql);
    }

    public function testCompileSelectWithOffset()
    {
        $driver = new PostgresDriver();
        $sql = $driver->compileSelect('users', ['*'], [], [], [], 10, 5);
        $this->assertStringContainsString('SELECT * FROM users', $sql);
        $this->assertStringContainsString('LIMIT 10', $sql);
        $this->assertStringContainsString('OFFSET 5', $sql);
    }

    public function testCompileUpdate()
    {
        $driver = new PostgresDriver();
        $sql = $driver->compileUpdate('users', ['name' => 'John', 'email' => 'john@example.com'], ['id = 1']);
        $this->assertStringContainsString('UPDATE users SET', $sql);
        $this->assertStringContainsString('name = :name', $sql);
        $this->assertStringContainsString('email = :email', $sql);
        $this->assertStringContainsString('WHERE id = 1', $sql);
    }

    public function testCompileDelete()
    {
        $driver = new PostgresDriver();
        $sql = $driver->compileDelete('users', ['id = 1']);
        $this->assertStringContainsString('DELETE FROM users', $sql);
        $this->assertStringContainsString('WHERE id = 1', $sql);
    }
}
