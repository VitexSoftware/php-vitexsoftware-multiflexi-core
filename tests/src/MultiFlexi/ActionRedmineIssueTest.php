<?php

declare(strict_types=1);

namespace Test\MultiFlexi;

use MultiFlexi\Action\RedmineIssue;
use MultiFlexi\CommonAction;
use PHPUnit\Framework\TestCase;

final class ActionRedmineIssueTest extends TestCase
{
    public function testExtendsCommonAction(): void
    {
        $this->assertTrue(is_a(RedmineIssue::class, CommonAction::class, true));
    }
}
