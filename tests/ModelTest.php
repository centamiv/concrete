<?php
namespace Tests;

use Concrete\Model;
use PHPUnit\Framework\TestCase;

class ModelTest extends TestCase
{
    public function testCanBeInstantiated()
    {
        // Create a dummy child class to test the abstract one
        $model = new class extends Model {};
        $this->assertInstanceOf(Model::class, $model);
    }

    public function testFillAndGet()
    {
        $model = new class extends Model {};
        $model->fill(['foo' => 'bar']);
        $this->assertEquals('bar', $model->get('foo'));
    }

    public function testSetAndGet()
    {
        $model = new class extends Model {};
        $model->set('baz', 123);
        $this->assertEquals(123, $model->get('baz'));
    }

    public function testColHelper()
    {
        $model = new class extends Model {
            public const TABLE = 'users';
        };

        // Test basic usage
        $this->assertEquals('users.name', $model::col('name'));

        // Test with table alias
        $this->assertEquals('u.name', $model::col('name', 'u'));

        // Test with column alias
        $this->assertEquals('users.name as user_name', $model::colAs('name', 'user_name'));

        // Test with both
        $this->assertEquals('u.name as user_name', $model::colAs('name', 'user_name', 'u'));
    }

    public function testDelete()
    {
        $model = new class extends Model {
            public const TABLE = 'users';
            public const PRIMARY_KEY = 'id';
        };

        // Simulate a loaded model
        $model->fill(['id' => 1, 'name' => 'Mario']);

        // Mock the Database connection and driver
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->expects($this->once())
            ->method('execute')
            ->with(['id0' => 1]);
        $stmt->expects($this->once())
            ->method('rowCount')
            ->willReturn(1);

        $pdo = $this->createMock(\PDO::class);
        $pdo->expects($this->once())
            ->method('prepare')
            ->with('DELETE FROM users WHERE id = :id0')
            ->willReturn($stmt);

        $driver = $this->createMock(\Concrete\Connection\DriverInterface::class);
        $driver->method('connect')->willReturn($pdo);
        $driver->method('compileDelete')->willReturn('DELETE FROM users WHERE id = :id0');

        \Concrete\Database::init($driver, 'h', 'd', 'u', 'p');

        $this->assertTrue($model->delete());
    }

    public function testDirtyState()
    {
        $model = new class extends Model {};

        // Hydrate simulates fetching from DB
        $model->hydrate(['foo' => 'bar', 'baz' => 123]);

        // Initially no dirty attributes
        $this->assertEmpty($model->getDirty());

        // Change an attribute
        $model->set('foo', 'qux');

        // Now foo should be dirty
        $dirty = $model->getDirty();
        $this->assertArrayHasKey('foo', $dirty);
        $this->assertEquals('qux', $dirty['foo']);
        $this->assertArrayNotHasKey('baz', $dirty);

        // Change it back to original
        $model->set('foo', 'bar');
        $this->assertEmpty($model->getDirty());
    }

    public function testSaveUpdatesOnlyDirtyAttributes()
    {
        $model = new class extends Model {
            public const TABLE = 'users';
            public const PRIMARY_KEY = 'id';
        };

        // Simulate existing record
        $model->hydrate(['id' => 1, 'name' => 'Mario', 'age' => 30]);

        // Only change name
        $model->set('name', 'Luigi');

        // Mock DB
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->expects($this->once())
            ->method('execute')
            ->with(['name' => 'Luigi', 'pk_id' => 1]); // age is NOT here

        $pdo = $this->createMock(\PDO::class);
        $pdo->expects($this->once())
            ->method('prepare')
            ->with('UPDATE users SET name = :name WHERE id = :pk_id')
            ->willReturn($stmt);

        $driver = $this->createMock(\Concrete\Connection\DriverInterface::class);
        $driver->method('connect')->willReturn($pdo);

        \Concrete\Database::init($driver, 'h', 'd', 'u', 'p');

        $model->save();

        // After save, model should be clean
        $this->assertEmpty($model->getDirty());
    }
}
