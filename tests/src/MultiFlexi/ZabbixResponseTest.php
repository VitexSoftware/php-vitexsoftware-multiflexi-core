<?php

declare(strict_types=1);

namespace Test\MultiFlexi;

use MultiFlexi\Zabbix\Response;
use PHPUnit\Framework\TestCase;

final class ZabbixResponseTest extends TestCase
{
    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(Response::class));
    }
}
