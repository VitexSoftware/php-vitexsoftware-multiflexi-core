<?php

declare(strict_types=1);

namespace Test\MultiFlexi;

use MultiFlexi\Action\WebHook;
use MultiFlexi\CommonAction;
use PHPUnit\Framework\TestCase;

final class ActionWebHookTest extends TestCase
{
    public function testExtendsCommonAction(): void
    {
        $this->assertTrue(is_a(WebHook::class, CommonAction::class, true));
    }
}
