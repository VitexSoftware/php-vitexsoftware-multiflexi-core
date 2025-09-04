<?php

declare(strict_types=1);

namespace Test\MultiFlexi;

use MultiFlexi\Action\Sleep;
use MultiFlexi\CommonAction;
use PHPUnit\Framework\TestCase;

final class ActionSleepTest extends TestCase
{
    public function testExtendsCommonAction(): void
    {
        $this->assertTrue(is_a(Sleep::class, CommonAction::class, true));
    }
}
