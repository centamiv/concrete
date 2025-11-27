<?php
namespace Tests;

use Concrete\Connection\DriverInterface;
use PHPUnit\Framework\TestCase;

class DriverInterfaceTest extends TestCase
{
    public function testInterfaceMethodsExist()
    {
        $reflection = new \ReflectionClass(DriverInterface::class);
        $this->assertTrue($reflection->hasMethod('connect'));
        $this->assertTrue($reflection->hasMethod('compileSelect'));
    }
}
