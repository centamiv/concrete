<?php
namespace Tests;

use Concrete\Model;
use PHPUnit\Framework\TestCase;

class JoinableTest extends TestCase
{
    public function testJoinAndLeftJoin()
    {
        $model = new class extends Model {
            public const TABLE = 'test';
        };

        $builder = $this->getMockBuilder(\Concrete\Query\Builder::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['sql'])
            ->getMock();
        $builder->table(get_class($model))
            ->join('table2', 'table1.id', '=', 'table2.ref_id')
            ->leftJoin('table3', 'table1.id', '=', 'table3.ref_id');
        $builder->expects($this->once())
            ->method('sql')
            ->willReturn('SELECT * FROM test INNER JOIN table2 ON table1.id = table2.ref_id LEFT JOIN table3 ON table1.id = table3.ref_id');
        $sql = $builder->sql();
        $this->assertStringContainsString('INNER JOIN table2 ON table1.id = table2.ref_id', $sql);
        $this->assertStringContainsString('LEFT JOIN table3 ON table1.id = table3.ref_id', $sql);
    }

}
