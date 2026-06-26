<?php

namespace Tests;

use Concrete\Query\When;
use Concrete\Query\Builder;
use Concrete\Database;
use Concrete\Connection\DriverInterface;
use PHPUnit\Framework\TestCase;

class WhenTest extends TestCase
{
    public function testBasicCaseWithIntResults()
    {
        $case = When::make()
            ->when('users.status', '=', 'active')->then(1)
            ->when('users.status', '=', 'pending')->then(2)
            ->else(0)
            ->as('status_code');

        $params = $case->getParams();

        $this->assertStringContainsString('CASE', $case->toSql());
        $this->assertStringContainsString('WHEN users.status = :', $case->toSql());
        $this->assertStringContainsString('THEN 1', $case->toSql());
        $this->assertStringContainsString('THEN 2', $case->toSql());
        $this->assertStringContainsString('ELSE 0', $case->toSql());
        $this->assertStringContainsString('END AS status_code', $case->toSql());

        $this->assertContains('active', $params);
        $this->assertContains('pending', $params);
    }

    public function testCaseWithStringResults()
    {
        $case = When::make()
            ->when('score', '>', 90)->then('A')
            ->when('score', '>', 80)->then('B')
            ->else('C')
            ->as('grade');

        $sql = $case->toSql();
        $params = $case->getParams();

        $this->assertStringContainsString('WHEN score > :', $sql);
        $this->assertStringContainsString('END AS grade', $sql);

        // THEN/ELSE string values are PDO params
        $this->assertContains('A', $params);
        $this->assertContains('B', $params);
        $this->assertContains('C', $params);
        $this->assertContains(90, $params);
        $this->assertContains(80, $params);
    }

    public function testCaseWithNullElse()
    {
        $case = When::make()
            ->when('col', '=', 'x')->then(1)
            ->else(null);

        $this->assertStringContainsString('ELSE NULL', $case->toSql());
    }

    public function testCaseWithoutElse()
    {
        $case = When::make()
            ->when('col', '=', 'x')->then(1);

        $sql = $case->toSql();
        $this->assertStringNotContainsString('ELSE', $sql);
        $this->assertStringContainsString('END', $sql);
    }

    public function testCaseWithoutAliasIsValid()
    {
        $case = When::make()
            ->when('col', '=', 'x')->then(1)
            ->else(0);

        $sql = $case->toSql();
        $this->assertStringEndsWith('END', $sql);
    }

    public function testInvalidColumnThrows()
    {
        $this->expectException(\InvalidArgumentException::class);
        When::make()->when('1=1; DROP TABLE users--', '=', 'x');
    }

    public function testInvalidOperatorThrows()
    {
        $this->expectException(\InvalidArgumentException::class);
        When::make()->when('col', 'OR 1=1--', 'x');
    }

    public function testInvalidAliasThrows()
    {
        $this->expectException(\InvalidArgumentException::class);
        When::make()
            ->when('col', '=', 'x')->then(1)
            ->as('bad alias!');
    }

    public function testMissingThenThrows()
    {
        $this->expectException(\LogicException::class);
        When::make()
            ->when('col', '=', 'x')
            ->when('col', '=', 'y');  // missing then() before second when()
    }

    public function testElseBeforeWhenThrows()
    {
        $this->expectException(\LogicException::class);
        When::make()
            ->when('col', '=', 'x')
            ->else(0);  // missing then()
    }

    public function testEmptyCaseThrows()
    {
        $this->expectException(\LogicException::class);
        When::make()->toSql();
    }

    public function testToStringEqualsToSql()
    {
        $case = When::make()
            ->when('col', '=', 'x')->then(1)
            ->else(0);

        $this->assertSame($case->toSql(), (string) $case);
    }

    public function testWhenInBuilderSelect()
    {
        $modelClass = get_class(new class {
            public const TABLE = 'users';
        });

        $case = When::make()
            ->when('users.status', '=', 'active')->then(1)
            ->else(0)
            ->as('is_active');

        $builder = new Builder();
        $builder->table($modelClass);
        $builder->select('id', $case);

        $driver = $this->createMock(DriverInterface::class);
        $driver->expects($this->once())
            ->method('compileSelect')
            ->with(
                'users',
                $this->callback(function (array $cols) {
                    return $cols[0] === 'users.id'
                        && str_contains($cols[1], 'CASE WHEN users.status')
                        && str_contains($cols[1], 'AS is_active');
                }),
                $this->anything(),
                [],
                [],
                null,
                null
            )
            ->willReturn('SELECT users.id, CASE WHEN … END AS is_active FROM users');

        Database::init($driver, 'h', 'd', 'u', 'p');

        $sql = $builder->sql();
        $this->assertStringContainsString('is_active', $sql);

        // Case params must be merged into builder params
        $builderParams = \Closure::bind(fn() => $this->params, $builder, Builder::class)();
        $this->assertContains('active', $builderParams);
    }

    public function testParamNamesAreUniqueAcrossMultipleInstances()
    {
        $case1 = When::make()->when('col', '=', 'a')->then(1)->else(0);
        $case2 = When::make()->when('col', '=', 'b')->then(1)->else(0);

        $keys1 = array_keys($case1->getParams());
        $keys2 = array_keys($case2->getParams());

        $this->assertEmpty(array_intersect($keys1, $keys2), 'Param names must not collide across instances');
    }
}
