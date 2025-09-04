<?php

declare(strict_types=1);

namespace Test\MultiFlexi;

use MultiFlexi\Action\Zabbix;
use MultiFlexi\CommonAction;
use PHPUnit\Framework\TestCase;

final class ActionZabbixTest extends TestCase
{
    public function testExtendsCommonAction(): void
    {
        $this->assertTrue(is_a(Zabbix::class, CommonAction::class, true));
    }
}
