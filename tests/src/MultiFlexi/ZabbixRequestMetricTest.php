<?php

declare(strict_types=1);

namespace Test\MultiFlexi;

use MultiFlexi\Zabbix\Request\Metric;
use PHPUnit\Framework\TestCase;

final class ZabbixRequestMetricTest extends TestCase
{
    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(Metric::class));
    }
}
