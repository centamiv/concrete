<?php

namespace Tests;

use Concrete\Query\Builder;
use Concrete\Query\Subquery;
use Concrete\Database;
use Concrete\Connection\DriverInterface;
use PHPUnit\Framework\TestCase;

class SubqueryTest extends TestCase
{
    // ── helpers ──────────────────────────────────────────────────────────────

    /** Build a Builder pre-wired to $table without needing a real Model class. */
    private function builderFor(string $tableName): Builder
    {
        static $classes = [];
        if (!isset($classes[$tableName])) {
            $safe = preg_replace('/[^a-zA-Z0-9_]/', '_', $tableName);
            $anon = eval("return new class extends \\Concrete\\Model { public const TABLE = '$safe'; };");
            $classes[$tableName] = get_class($anon);
        }
        $b = new Builder();
        $b->table($classes[$tableName]);
        return $b;
    }

    /**
     * Return a driver mock that compiles SQL trivially and captures wheres/params
     * passed to compileSelect into the provided reference variables.
     */
    private function capturingDriver(?array &$wheres = null, string $sql = 'SELECT 1'): DriverInterface
    {
        $driver = $this->createMock(DriverInterface::class);
        $driver->method('compileSelect')
            ->willReturnCallback(function ($t, $c, $w) use (&$wheres, $sql) {
                $wheres = $w;
                return $sql;
            });
        return $driver;
    }

    // ── whereIn with array ────────────────────────────────────────────────────

    public function testWhereInArray()
    {
        Database::init($this->capturingDriver($wheres), 'h', 'd', 'u', 'p');

        $b = $this->builderFor('orders');
        $b->whereIn('orders.status', ['pending', 'processing']);

        $b->sql();

        $params = $b->getParams();
        $this->assertCount(2, $params);
        $this->assertContains('pending', $params);
        $this->assertContains('processing', $params);
        $this->assertStringContainsString('orders.status IN (', $wheres[0]);
        $this->assertStringContainsString(':', $wheres[0]);
    }

    public function testWhereInEmptyArrayProducesAlwaysFalse()
    {
        Database::init($this->capturingDriver($wheres), 'h', 'd', 'u', 'p');

        $b = $this->builderFor('orders');
        $b->whereIn('orders.id', []);
        $b->sql();

        $this->assertSame(['1 = 0'], $wheres);
        $this->assertEmpty($b->getParams());
    }

    // ── whereNotIn with array ─────────────────────────────────────────────────

    public function testWhereNotInArray()
    {
        Database::init($this->capturingDriver($wheres), 'h', 'd', 'u', 'p');

        $b = $this->builderFor('users');
        $b->whereNotIn('users.id', [1, 2, 3]);
        $b->sql();

        $params = $b->getParams();
        $this->assertCount(3, $params);
        $this->assertContains(1, $params);
        $this->assertContains(3, $params);
        $this->assertStringContainsString('users.id NOT IN (', $wheres[0]);
    }

    public function testWhereNotInEmptyArrayProducesAlwaysTrue()
    {
        Database::init($this->capturingDriver($wheres), 'h', 'd', 'u', 'p');

        $b = $this->builderFor('users');
        $b->whereNotIn('users.id', []);
        $b->sql();

        $this->assertSame(['1 = 1'], $wheres);
        $this->assertEmpty($b->getParams());
    }

    // ── whereIn / whereNotIn with subquery ────────────────────────────────────

    public function testWhereInSubquery()
    {
        $subSql = 'SELECT id FROM orders WHERE orders.status = :orders_status0';
        Database::init($this->capturingDriver($wheres, $subSql), 'h', 'd', 'u', 'p');

        $sub = $this->builderFor('orders');
        $sub->where('orders.status', '=', 'paid');

        $parent = $this->builderFor('users');
        $parent->whereIn('users.id', $sub);
        $parent->sql();

        // Parent params must contain the subquery value (remapped key)
        $params = $parent->getParams();
        $this->assertContains('paid', $params);
        $this->assertStringContainsString('users.id IN (', $wheres[0]);
        $this->assertStringContainsString('SELECT id FROM orders', $wheres[0]);
    }

    public function testWhereNotInSubquery()
    {
        $subSql = 'SELECT id FROM banned WHERE banned.reason = :banned_reason0';
        Database::init($this->capturingDriver($wheres, $subSql), 'h', 'd', 'u', 'p');

        $sub = $this->builderFor('banned');
        $sub->where('banned.reason', '=', 'spam');

        $parent = $this->builderFor('users');
        $parent->whereNotIn('users.id', $sub);
        $parent->sql();

        $params = $parent->getParams();
        $this->assertContains('spam', $params);
        $this->assertStringContainsString('users.id NOT IN (', $wheres[0]);
    }

    // ── whereExists / whereNotExists ──────────────────────────────────────────

    public function testWhereExists()
    {
        $subSql = 'SELECT 1 FROM orders WHERE orders.user_id = users.id';
        Database::init($this->capturingDriver($wheres, $subSql), 'h', 'd', 'u', 'p');

        $sub = $this->builderFor('orders');
        $sub->whereColumn('orders.user_id', '=', 'users.id');

        $parent = $this->builderFor('users');
        $parent->whereExists($sub);
        $parent->sql();

        $this->assertStringContainsString('EXISTS (', $wheres[0]);
        $this->assertStringContainsString('SELECT 1 FROM orders', $wheres[0]);
    }

    public function testWhereNotExists()
    {
        $subSql = 'SELECT 1 FROM bans WHERE bans.user_id = users.id';
        Database::init($this->capturingDriver($wheres, $subSql), 'h', 'd', 'u', 'p');

        $sub = $this->builderFor('bans');
        $sub->whereColumn('bans.user_id', '=', 'users.id');

        $parent = $this->builderFor('users');
        $parent->whereNotExists($sub);
        $parent->sql();

        $this->assertStringContainsString('NOT EXISTS (', $wheres[0]);
    }

    // ── whereColumn ───────────────────────────────────────────────────────────

    public function testWhereColumn()
    {
        Database::init($this->capturingDriver($wheres), 'h', 'd', 'u', 'p');

        $b = $this->builderFor('orders');
        $b->whereColumn('orders.user_id', '=', 'users.id');
        $b->sql();

        // No PDO params — column-to-column comparison
        $this->assertEmpty($b->getParams());
        $this->assertSame(['orders.user_id = users.id'], $wheres);
    }

    public function testWhereColumnInvalidIdentifierThrows()
    {
        $this->expectException(\InvalidArgumentException::class);
        $b = $this->builderFor('orders');
        $b->whereColumn('orders.user_id', '=', '1; DROP TABLE users--');
    }

    public function testWhereColumnInvalidOperatorThrows()
    {
        $this->expectException(\InvalidArgumentException::class);
        $b = $this->builderFor('orders');
        $b->whereColumn('orders.id', 'LIKE', 'users.id');
    }

    // ── param name collision: two subqueries in the same parent ───────────────

    public function testSubqueryParamsDoNotCollide()
    {
        // Both sub1 and sub2 have where on 'col' → same raw param name 'col0'.
        // After embedding they must use distinct remapped keys.
        $driver = $this->createMock(DriverInterface::class);
        $driver->method('compileSelect')->willReturnCallback(
            fn($t, $c, $w) => 'SELECT 1 FROM ' . $t . (empty($w) ? '' : ' WHERE ' . implode(' AND ', $w))
        );
        Database::init($driver, 'h', 'd', 'u', 'p');

        $sub1 = $this->builderFor('a');
        $sub1->where('col', '=', 'val1');

        $sub2 = $this->builderFor('b');
        $sub2->where('col', '=', 'val2');

        $parent = $this->builderFor('users');
        $parent->whereIn('users.id', $sub1);
        $parent->whereIn('users.name', $sub2);
        $parent->sql();

        $params = $parent->getParams();
        $this->assertContains('val1', $params);
        $this->assertContains('val2', $params);
        $this->assertCount(2, $params);
        // Keys must be different
        $this->assertCount(2, array_unique(array_keys($params)));
    }

    // ── Subquery in SELECT ──────────────────────────────────────────

    public function testSubqueryInSelect()
    {
        $subSql = 'SELECT COUNT(*) FROM orders WHERE orders.user_id = users.id';
        $calls = 0;
        $driver = $this->createMock(DriverInterface::class);
        $driver->method('compileSelect')->willReturnCallback(
            function ($t, $cols, $w) use ($subSql, &$calls) {
                $calls++;
                if ($calls === 1) {
                    return $subSql;  // subquery compilation
                }
                return 'SELECT ' . implode(', ', $cols) . ' FROM ' . $t;
            }
        );
        Database::init($driver, 'h', 'd', 'u', 'p');

        $sub = $this->builderFor('orders');
        $sub->whereColumn('orders.user_id', '=', 'users.id');

        $modelClass = get_class(new class { public const TABLE = 'users'; });
        $parent = new Builder();
        $parent->table($modelClass);
        $parent->select('id', Subquery::make($sub)->as('order_count'));

        $sql = $parent->sql();
        $this->assertStringContainsString('order_count', $sql);
    }

    public function testSubqueryParamsRemapped()
    {
        $driver = $this->createMock(DriverInterface::class);
        $driver->method('compileSelect')->willReturnCallback(
            fn($t, $c, $w) => 'SELECT 1 FROM ' . $t . (empty($w) ? '' : ' WHERE ' . implode(' AND ', $w))
        );
        Database::init($driver, 'h', 'd', 'u', 'p');

        $sub = $this->builderFor('orders');
        $sub->where('orders.status', '=', 'paid');

        $expr = Subquery::make($sub)->as('order_count');

        $params = $expr->getParams();
        $this->assertContains('paid', $params);
        // Raw param name ('orders_status0') must NOT appear — it should be remapped
        $this->assertNotContains('orders_status0', array_keys($params));
    }

    public function testSubqueryInvalidAliasThrows()
    {
        $driver = $this->createMock(DriverInterface::class);
        $driver->method('compileSelect')->willReturn('SELECT 1');
        Database::init($driver, 'h', 'd', 'u', 'p');

        $this->expectException(\InvalidArgumentException::class);
        Subquery::make($this->builderFor('t'))->as('bad alias!');
    }

    public function testSubqueryToStringEqualsToSql()
    {
        $driver = $this->createMock(DriverInterface::class);
        $driver->method('compileSelect')->willReturn('SELECT COUNT(*) FROM t');
        Database::init($driver, 'h', 'd', 'u', 'p');

        $expr = Subquery::make($this->builderFor('t'))->as('cnt');
        $this->assertSame($expr->toSql(), (string) $expr);
    }
}
