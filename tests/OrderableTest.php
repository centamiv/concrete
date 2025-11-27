<?php
namespace Tests;

use Concrete\Model;
use PHPUnit\Framework\TestCase;

class OrderableTest extends TestCase
{
    public function testOrderBy()
    {
        $model = new class extends Model {
            public const TABLE = 'test';
        };

        $builder = $this->getMockBuilder(\Concrete\Query\Builder::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['sql'])
            ->getMock();
        $builder->table(get_class($model))
            ->orderBy('foo', 'DESC')
            ->orderBy('bar');
        $builder->expects($this->once())
            ->method('sql')
            ->willReturn('SELECT * FROM test ORDER BY foo DESC, bar ASC');
        $sql = $builder->sql();
        $this->assertStringContainsString('ORDER BY foo DESC, bar ASC', $sql);
    }

}
