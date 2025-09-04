<?php

declare(strict_types=1);

namespace Test\MultiFlexi;

use MultiFlexi\Action\LaunchJob;
use MultiFlexi\CommonAction;
use PHPUnit\Framework\TestCase;

final class ActionLaunchJobTest extends TestCase
{
    public function testExtendsCommonAction(): void
    {
        $this->assertTrue(is_a(LaunchJob::class, CommonAction::class, true));
    }
}
