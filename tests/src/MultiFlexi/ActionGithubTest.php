<?php

declare(strict_types=1);

namespace Test\MultiFlexi;

use MultiFlexi\Action\Github;
use MultiFlexi\CommonAction;
use PHPUnit\Framework\TestCase;

final class ActionGithubTest extends TestCase
{
    public function testExtendsCommonAction(): void
    {
        $this->assertTrue(is_a(Github::class, CommonAction::class, true));
    }
}
