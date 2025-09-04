<?php

declare(strict_types=1);

namespace Test\MultiFlexi;

use MultiFlexi\Action\CustomCommand;
use MultiFlexi\CommonAction;
use PHPUnit\Framework\TestCase;

final class ActionCustomCommandTest extends TestCase
{
    public function testExtendsCommonAction(): void
    {
        $this->assertTrue(is_a(CustomCommand::class, CommonAction::class, true));
    }
}
