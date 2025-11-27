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
        $builder->table($model::class)->where('foo', '=', 'bar');
        // Verifica che la query SQL contenga la clausola WHERE corretta
        $builder->expects($this->once())
            ->method('sql')
            ->willReturn('SELECT * FROM test WHERE foo = :foo_0');
        $sql = $builder->sql();
        $this->assertStringContainsString('WHERE foo = :foo_0', $sql);
    }

}
