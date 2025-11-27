<?php
namespace Tests;

use Concrete\Connection\MysqlDriver;
use PHPUnit\Framework\TestCase;

class MysqlDriverTest extends TestCase
{
    public function testCompileSelect()
    {
        $driver = new MysqlDriver();
        $sql = $driver->compileSelect('users', ['id', 'name'], ['id = 1'], ['name ASC'], ['INNER JOIN roles ON users.role_id = roles.id']);
        $this->assertStringContainsString('SELECT id, name FROM users', $sql);
        $this->assertStringContainsString('INNER JOIN roles ON users.role_id = roles.id', $sql);
        $this->assertStringContainsString('WHERE id = 1', $sql);
        $this->assertStringContainsString('ORDER BY name ASC', $sql);
    }
}
