<?php
namespace Tests;

use Concrete\Query\Builder;
use Concrete\Database;
use Concrete\Connection\DriverInterface;
use PHPUnit\Framework\TestCase;

class BuilderTest extends TestCase
{
    public function testTableAndSelect()
    {
        $modelClass = get_class(new class {
            public const TABLE = 'users';
        });

        $builder = new Builder();
        $builder->table($modelClass);
        $builder->select('name', 'email');

        // Mock Driver
        $driver = $this->createMock(DriverInterface::class);
        $driver->expects($this->once())
            ->method('compileSelect')
            ->with('users', ['users.name', 'users.email'], [], [], [], null, null)
            ->willReturn('SELECT users.name, users.email FROM users');

        // Mock Database
        // Note: Database::init is static, so we need to be careful.
        // For unit testing Builder::sql(), we just need the driver.
        Database::init($driver, 'h', 'd', 'u', 'p');

        $this->assertEquals('SELECT users.name, users.email FROM users', $builder->sql());
    }

    public function testTakeAndSkip()
    {
        $modelClass = get_class(new class {
            public const TABLE = 'users';
        });

        $builder = new Builder();
        $builder->table($modelClass);
        $builder->take(10);
        $builder->skip(5);

        $driver = $this->createMock(DriverInterface::class);
        $driver->expects($this->once())
            ->method('compileSelect')
            ->with('users', ['*'], [], [], [], 10, 5)
            ->willReturn('SELECT * FROM users LIMIT 10 OFFSET 5');

        Database::init($driver, 'h', 'd', 'u', 'p');

        $this->assertEquals('SELECT * FROM users LIMIT 10 OFFSET 5', $builder->sql());
    }

    public function testCount()
    {
        $modelClass = get_class(new class {
            public const TABLE = 'users';
        });

        $builder = new Builder();
        $builder->table($modelClass);

        // Mock PDOStatement
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->expects($this->once())
            ->method('execute')
            ->with([]);
        $stmt->expects($this->once())
            ->method('fetchColumn')
            ->willReturn(42);

        // Mock PDO
        $pdo = $this->createMock(\PDO::class);
        $pdo->expects($this->once())
            ->method('prepare')
            ->with('SELECT COUNT(*) FROM users')
            ->willReturn($stmt);

        // Mock Driver to return PDO
        $driver = $this->createMock(DriverInterface::class);
        $driver->method('connect')->willReturn($pdo);
        $driver->method('compileSelect')->willReturn('SELECT COUNT(*) FROM users');

        Database::init($driver, 'h', 'd', 'u', 'p');

        $this->assertEquals(42, $builder->count());
    }

    public function testFirst()
    {
        // Define a dummy model class that can be instantiated
        $modelClass = get_class(new class extends \Concrete\Model {
            public const TABLE = 'users';
        });

        $builder = new Builder();
        $builder->table($modelClass);

        // Mock PDOStatement
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->expects($this->once())
            ->method('execute')
            ->with([]);
        $stmt->expects($this->exactly(2)) // Called once for fetch loop, once for false
            ->method('fetch')
            ->willReturnOnConsecutiveCalls(['name' => 'Mario'], false);

        // Mock PDO
        $pdo = $this->createMock(\PDO::class);
        $pdo->expects($this->once())
            ->method('prepare')
            ->with('SELECT * FROM users LIMIT 1')
            ->willReturn($stmt);

        // Mock Driver
        $driver = $this->createMock(DriverInterface::class);
        $driver->method('connect')->willReturn($pdo);
        $driver->method('compileSelect')->willReturn('SELECT * FROM users LIMIT 1');

        Database::init($driver, 'h', 'd', 'u', 'p');

        $result = $builder->first();
        $this->assertInstanceOf($modelClass, $result);
        $this->assertEquals('Mario', $result->get('name'));
    }

    public function testExists()
    {
        $modelClass = get_class(new class extends \Concrete\Model {
            public const TABLE = 'users';
        });

        $builder = new Builder();
        $builder->table($modelClass);

        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->expects($this->once())->method('execute');
        $stmt->expects($this->once())
            ->method('fetchAll')
            ->willReturn([['id' => 1]]);

        $pdo = $this->createMock(\PDO::class);
        $pdo->expects($this->once())->method('prepare')->willReturn($stmt);

        $driver = $this->createMock(DriverInterface::class);
        $driver->method('connect')->willReturn($pdo);
        $driver->method('compileSelect')->willReturn('SELECT * FROM users LIMIT 1');

        Database::init($driver, 'h', 'd', 'u', 'p');

        $this->assertTrue($builder->exists());
    }

    public function testUpdate()
    {
        $modelClass = get_class(new class {
            public const TABLE = 'users';
        });

        $builder = new Builder();
        $builder->table($modelClass);
        $builder->where('id', '=', 1);

        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->expects($this->once())
            ->method('execute')
            ->with(['name' => 'Luigi', 'id0' => 1]);
        $stmt->expects($this->once())
            ->method('rowCount')
            ->willReturn(1);

        $pdo = $this->createMock(\PDO::class);
        $pdo->expects($this->once())
            ->method('prepare')
            ->with('UPDATE users SET name = :name WHERE id = :id0')
            ->willReturn($stmt);

        $driver = $this->createMock(DriverInterface::class);
        $driver->method('connect')->willReturn($pdo);
        $driver->method('compileUpdate')->willReturn('UPDATE users SET name = :name WHERE id = :id0');

        Database::init($driver, 'h', 'd', 'u', 'p');

        $this->assertEquals(1, $builder->update(['name' => 'Luigi']));
    }

    public function testDelete()
    {
        $modelClass = get_class(new class {
            public const TABLE = 'users';
        });

        $builder = new Builder();
        $builder->table($modelClass);
        $builder->where('active', '=', 0);

        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->expects($this->once())
            ->method('execute')
            ->with(['active0' => 0]);
        $stmt->expects($this->once())
            ->method('rowCount')
            ->willReturn(5);

        $pdo = $this->createMock(\PDO::class);
        $pdo->expects($this->once())
            ->method('prepare')
            ->with('DELETE FROM users WHERE active = :active0')
            ->willReturn($stmt);

        $driver = $this->createMock(DriverInterface::class);
        $driver->method('connect')->willReturn($pdo);
        $driver->method('compileDelete')->willReturn('DELETE FROM users WHERE active = :active0');

        Database::init($driver, 'h', 'd', 'u', 'p');

        $this->assertEquals(5, $builder->delete());
    }

    public function testGetRows()
    {
        $modelClass = get_class(new class {
            public const TABLE = 'users';
        });
        $builder = new Builder();
        $builder->table($modelClass);

        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->expects($this->once())
            ->method('execute');
        $stmt->expects($this->once())
            ->method('fetchAll')
            ->with(\PDO::FETCH_ASSOC)
            ->willReturn([['id' => 1, 'name' => 'Mario']]);

        $pdo = $this->createMock(\PDO::class);
        $pdo->expects($this->once())
            ->method('prepare')
            ->willReturn($stmt);

        $driver = $this->createMock(\Concrete\Connection\DriverInterface::class);
        $driver->method('connect')->willReturn($pdo);
        $driver->method('compileSelect')->willReturn('SELECT * FROM users');

        \Concrete\Database::init($driver, 'h', 'd', 'u', 'p');

        $rows = $builder->getRows();
        $this->assertIsArray($rows);
        $this->assertIsArray($rows[0]);
        $this->assertEquals('Mario', $rows[0]['name']);
    }

    public function testFirstRow()
    {
        $modelClass = get_class(new class {
            public const TABLE = 'users';
        });
        $builder = new Builder();
        $builder->table($modelClass);

        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->expects($this->once())
            ->method('execute');
        $stmt->expects($this->once())
            ->method('fetchAll')
            ->with(\PDO::FETCH_ASSOC)
            ->willReturn([['id' => 1, 'name' => 'Mario']]);

        $pdo = $this->createMock(\PDO::class);
        $pdo->expects($this->once())
            ->method('prepare')
            ->willReturn($stmt);

        $driver = $this->createMock(\Concrete\Connection\DriverInterface::class);
        $driver->method('connect')->willReturn($pdo);
        // firstRow calls take(1) which modifies the query
        $driver->method('compileSelect')->willReturn('SELECT * FROM users LIMIT 1');

        \Concrete\Database::init($driver, 'h', 'd', 'u', 'p');

        $row = $builder->firstRow();
        $this->assertIsArray($row);
        $this->assertEquals('Mario', $row['name']);
    }
}
