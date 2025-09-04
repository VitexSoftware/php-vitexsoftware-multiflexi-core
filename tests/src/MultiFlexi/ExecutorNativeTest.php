<?php

declare(strict_types=1);

namespace Test\MultiFlexi;

use MultiFlexi\CommonExecutor;
use MultiFlexi\Executor\Native;
use PHPUnit\Framework\TestCase;

final class ExecutorNativeTest extends TestCase
{
    public function testExtendsCommonExecutor(): void
    {
        $this->assertTrue(is_a(Native::class, CommonExecutor::class, true));
    }
}
