<?php

declare(strict_types=1);

namespace Test\MultiFlexi;

use MultiFlexi\Action\Stop;
use MultiFlexi\CommonAction;
use PHPUnit\Framework\TestCase;

final class ActionStopTest extends TestCase
{
    public function testExtendsCommonAction(): void
    {
        $this->assertTrue(is_a(Stop::class, CommonAction::class, true));
    }
}
