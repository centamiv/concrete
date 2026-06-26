<?php

namespace Tests;

use Concrete\Query\Builder;
use Concrete\Database;
use Concrete\Connection\DriverInterface;
use PHPUnit\Framework\TestCase;

class UnionTest extends TestCase
{
    // ── helpers ──────────────────────────────────────────────────────────────

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

    /** Trivial driver that compiles SQL from its arguments without a real DB. */
    private function trivialDriver(): DriverInterface
    {
        $driver = $this->createMock(DriverInterface::class);
        $driver->method('compileSelect')->willReturnCallback(
            function (string $t, array $c, array $w, array $o, array $j, ?int $l, ?int $off): string {
                $sql = 'SELECT ' . implode(', ', $c) . ' FROM ' . $t;
                if (!empty($j)) {
                    $sql .= ' ' . implode(' ', $j);
                }
                if (!empty($w)) {
                    $sql .= ' WHERE ' . implode(' AND ', $w);
                }
                if (!empty($o)) {
                    $sql .= ' ORDER BY ' . implode(', ', $o);
                }
                if ($l !== null) {
                    $sql .= ' LIMIT ' . $l;
                }
                if ($off !== null) {
                    $sql .= ' OFFSET ' . $off;
                }
                return $sql;
            }
        );
        return $driver;
    }

    // ── basic UNION ───────────────────────────────────────────────────────────

    public function testBasicUnion()
    {
        Database::init($this->trivialDriver(), 'h', 'd', 'u', 'p');

        $q1 = $this->builderFor('users');
        $q1->where('users.role', '=', 'admin');

        $q2 = $this->builderFor('users');
        $q2->where('users.role', '=', 'moderator');

        $q1->union($q2);
        $sql = $q1->sql();

        $this->assertStringContainsString(') UNION (', $sql);
        $this->assertStringNotContainsString('UNION ALL', $sql);

        $params = $q1->getParams();
        $this->assertContains('admin', $params);
        $this->assertContains('moderator', $params);
        $this->assertCount(2, $params);
    }

    // ── UNION ALL ─────────────────────────────────────────────────────────────

    public function testUnionAll()
    {
        Database::init($this->trivialDriver(), 'h', 'd', 'u', 'p');

        $q1 = $this->builderFor('logs');
        $q1->where('logs.level', '=', 'error');

        $q2 = $this->builderFor('archived_logs');
        $q2->where('archived_logs.level', '=', 'error');

        $q1->unionAll($q2);
        $sql = $q1->sql();

        $this->assertStringContainsString('UNION ALL', $sql);
    }

    // ── multiple chained UNIONs ───────────────────────────────────────────────

    public function testMultipleUnions()
    {
        Database::init($this->trivialDriver(), 'h', 'd', 'u', 'p');

        $q1 = $this->builderFor('a');
        $q2 = $this->builderFor('b');
        $q3 = $this->builderFor('c');

        $q1->union($q2)->union($q3);
        $sql = $q1->sql();

        $this->assertSame(2, substr_count($sql, 'UNION'));
    }

    // ── param collision prevention ────────────────────────────────────────────

    public function testUnionParamsDoNotCollide()
    {
        // Both parts filter on 'status': same raw param name without remapping.
        Database::init($this->trivialDriver(), 'h', 'd', 'u', 'p');

        $q1 = $this->builderFor('orders');
        $q1->where('orders.status', '=', 'paid');

        $q2 = $this->builderFor('orders');
        $q2->where('orders.status', '=', 'refunded');

        $q1->union($q2);
        $q1->sql();  // params are merged during sql() compilation
        $params = $q1->getParams();

        $this->assertContains('paid', $params);
        $this->assertContains('refunded', $params);
        $this->assertCount(2, $params);
        $this->assertCount(2, array_unique(array_keys($params)));
    }

    // ── no params ─────────────────────────────────────────────────────────────

    public function testUnionWithNoParams()
    {
        Database::init($this->trivialDriver(), 'h', 'd', 'u', 'p');

        $q1 = $this->builderFor('users');
        $q2 = $this->builderFor('admins');

        $q1->union($q2);
        $sql = $q1->sql();

        $this->assertStringContainsString('UNION', $sql);
        $this->assertEmpty($q1->getParams());
    }

    // ── LIMIT applied to whole union ──────────────────────────────────────────

    public function testUnionWithLimit()
    {
        Database::init($this->trivialDriver(), 'h', 'd', 'u', 'p');

        $q1 = $this->builderFor('users');
        $q2 = $this->builderFor('users');
        $q2->where('users.active', '=', 0);

        $q1->union($q2)->take(5);
        $sql = $q1->sql();

        // LIMIT must appear after the closing paren of the last union part
        $this->assertMatchesRegularExpression('/\)\s+LIMIT 5$/', $sql);
        // LIMIT must NOT appear inside the first part
        $firstPart = substr($sql, 1, strpos($sql, ') UNION') - 1);
        $this->assertStringNotContainsString('LIMIT', $firstPart);
    }

    // ── ORDER BY applied to whole union ──────────────────────────────────────

    public function testUnionWithOrderBy()
    {
        Database::init($this->trivialDriver(), 'h', 'd', 'u', 'p');

        $q1 = $this->builderFor('users');
        $q2 = $this->builderFor('users');

        $q1->union($q2)->orderBy('users.name', 'ASC');
        $sql = $q1->sql();

        // ORDER BY must appear after the union, not inside the first part
        $this->assertMatchesRegularExpression('/\)\s+ORDER BY users\.name ASC$/', $sql);
        $firstPart = substr($sql, 1, strpos($sql, ') UNION') - 1);
        $this->assertStringNotContainsString('ORDER BY', $firstPart);
    }

    // ── LIMIT + OFFSET ────────────────────────────────────────────────────────

    public function testUnionWithLimitAndOffset()
    {
        Database::init($this->trivialDriver(), 'h', 'd', 'u', 'p');

        $q1 = $this->builderFor('users');
        $q2 = $this->builderFor('users');

        $q1->union($q2)->take(10)->skip(20);
        $sql = $q1->sql();

        $this->assertStringEndsWith('LIMIT 10 OFFSET 20', $sql);
    }

    // ── OFFSET without LIMIT ──────────────────────────────────────────────────

    public function testUnionOffsetWithoutLimitAddsMaxLimit()
    {
        Database::init($this->trivialDriver(), 'h', 'd', 'u', 'p');

        $q1 = $this->builderFor('users');
        $q2 = $this->builderFor('users');

        $q1->union($q2)->skip(10);
        $sql = $q1->sql();

        $this->assertStringContainsString('LIMIT ' . PHP_INT_MAX, $sql);
        $this->assertStringContainsString('OFFSET 10', $sql);
    }

    // ── no-union path is unchanged ────────────────────────────────────────────

    public function testNoUnionProducesPlainSelect()
    {
        Database::init($this->trivialDriver(), 'h', 'd', 'u', 'p');

        $q = $this->builderFor('users');
        $q->where('users.active', '=', 1)->take(3);
        $sql = $q->sql();

        $this->assertStringNotContainsString('UNION', $sql);
        $this->assertStringContainsString('LIMIT 3', $sql);
    }
}
