<?php
namespace Tests;

use Concrete\Model;
use PHPUnit\Framework\TestCase;

class FilterableTest extends TestCase
{
    public function testWhere()
    {
        $model = new class extends Model {
            public const TABLE = 'test';
        };

        $builder = $this->getMockBuilder(\Concrete\Query\Builder::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['sql'])
            ->getMock();
        $builder->table(get_class($model))->where('foo', '=', 'bar');
        // Verify that the SQL query contains the correct WHERE clause
        $builder->expects($this->once())
            ->method('sql')
            ->willReturn('SELECT * FROM test WHERE foo = :foo_0');
        $sql = $builder->sql();
        $this->assertStringContainsString('WHERE foo = :foo_0', $sql);
    }

}
