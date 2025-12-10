<?php
namespace Tests;

use Concrete\Connection\SqlServerDriver;
use PHPUnit\Framework\TestCase;

class SqlServerDriverTest extends TestCase
{
    public function testCompileSelect()
    {
        $driver = new SqlServerDriver();
        $sql = $driver->compileSelect('users', ['id', 'name'], ['id = 1'], ['name ASC'], ['INNER JOIN roles ON users.role_id = roles.id']);
        $this->assertStringContainsString('SELECT id, name FROM users', $sql);
        $this->assertStringContainsString('INNER JOIN roles ON users.role_id = roles.id', $sql);
        $this->assertStringContainsString('WHERE id = 1', $sql);
        $this->assertStringContainsString('ORDER BY name ASC', $sql);
    }

    public function testCompileSelectWithLimit()
    {
        $driver = new SqlServerDriver();
        $sql = $driver->compileSelect('users', ['*'], [], [], [], 10, null);
        $this->assertStringContainsString('SELECT * FROM users', $sql);
        // SQL Server uses OFFSET...FETCH syntax
        $this->assertStringContainsString('OFFSET 0 ROWS', $sql);
        $this->assertStringContainsString('FETCH NEXT 10 ROWS ONLY', $sql);
    }

    public function testCompileSelectWithOffset()
    {
        $driver = new SqlServerDriver();
        $sql = $driver->compileSelect('users', ['*'], [], [], [], 10, 5);
        $this->assertStringContainsString('SELECT * FROM users', $sql);
        $this->assertStringContainsString('OFFSET 5 ROWS', $sql);
        $this->assertStringContainsString('FETCH NEXT 10 ROWS ONLY', $sql);
    }

    public function testCompileSelectWithOnlyOffset()
    {
        $driver = new SqlServerDriver();
        $sql = $driver->compileSelect('users', ['*'], [], [], [], null, 5);
        $this->assertStringContainsString('SELECT * FROM users', $sql);
        $this->assertStringContainsString('OFFSET 5 ROWS', $sql);
        $this->assertStringNotContainsString('FETCH', $sql);
    }

    public function testCompileSelectWithLimitRequiresOrderBy()
    {
        $driver = new SqlServerDriver();
        $sql = $driver->compileSelect('users', ['*'], [], [], [], 10, null);
        // SQL Server requires ORDER BY when using OFFSET/FETCH
        $this->assertStringContainsString('ORDER BY', $sql);
    }

    public function testCompileUpdate()
    {
        $driver = new SqlServerDriver();
        $sql = $driver->compileUpdate('users', ['name' => 'John', 'email' => 'john@example.com'], ['id = 1']);
        $this->assertStringContainsString('UPDATE users SET', $sql);
        $this->assertStringContainsString('name = :name', $sql);
        $this->assertStringContainsString('email = :email', $sql);
        $this->assertStringContainsString('WHERE id = 1', $sql);
    }

    public function testCompileDelete()
    {
        $driver = new SqlServerDriver();
        $sql = $driver->compileDelete('users', ['id = 1']);
        $this->assertStringContainsString('DELETE FROM users', $sql);
        $this->assertStringContainsString('WHERE id = 1', $sql);
    }
}
