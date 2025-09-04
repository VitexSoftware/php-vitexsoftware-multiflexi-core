<?php

declare(strict_types=1);

namespace Test\MultiFlexi;

use MultiFlexi\Action\TriggerJenkins;
use MultiFlexi\CommonAction;
use PHPUnit\Framework\TestCase;

final class ActionTriggerJenkinsTest extends TestCase
{
    public function testExtendsCommonAction(): void
    {
        $this->assertTrue(is_a(TriggerJenkins::class, CommonAction::class, true));
    }
}
