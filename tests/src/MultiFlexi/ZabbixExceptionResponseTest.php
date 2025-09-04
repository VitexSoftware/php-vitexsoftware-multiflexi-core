<?php

declare(strict_types=1);

namespace Test\MultiFlexi;

use MultiFlexi\Zabbix\Exception\ZabbixResponseException;
use PHPUnit\Framework\TestCase;

final class ZabbixExceptionResponseTest extends TestCase
{
    public function testIsThrowable(): void
    {
        $this->assertTrue(is_a(ZabbixResponseException::class, \Throwable::class, true));
    }
}
