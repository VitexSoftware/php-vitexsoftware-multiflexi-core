<?php

declare(strict_types=1);

namespace Test\MultiFlexi;

use MultiFlexi\Zabbix\Exception\ZabbixNetworkException;
use PHPUnit\Framework\TestCase;

final class ZabbixExceptionNetworkTest extends TestCase
{
    public function testIsThrowable(): void
    {
        $this->assertTrue(is_a(ZabbixNetworkException::class, \Throwable::class, true));
    }
}
