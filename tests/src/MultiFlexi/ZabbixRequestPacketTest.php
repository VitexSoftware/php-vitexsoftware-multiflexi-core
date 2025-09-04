<?php

declare(strict_types=1);

namespace Test\MultiFlexi;

use MultiFlexi\Zabbix\Request\Packet;
use PHPUnit\Framework\TestCase;

final class ZabbixRequestPacketTest extends TestCase
{
    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(Packet::class));
    }
}
