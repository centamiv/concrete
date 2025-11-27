<?php
namespace Tests;

use PHPUnit\Framework\TestCase;
use Concrete\Connection\DriverInterface;
use Concrete\Database;

class DatabaseTest extends TestCase
{
    public function testInitAndGetConnection()
    {
        $mockDriver = $this->createMock(DriverInterface::class);
        $mockPdo = $this->createMock(\PDO::class);
        $mockDriver->method('connect')->willReturn($mockPdo);

        Database::init($mockDriver, 'host', 'db', 'user', 'pass');
        $this->assertSame($mockPdo, Database::getConnection());
        $this->assertSame($mockDriver, Database::getDriver());
    }
}
