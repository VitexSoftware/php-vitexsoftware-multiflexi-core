<?php

declare(strict_types=1);

namespace Test\MultiFlexi;

use MultiFlexi\CommonExecutor;
use MultiFlexi\Executor\Azure;
use PHPUnit\Framework\TestCase;

final class ExecutorAzureTest extends TestCase
{
    public function testExtendsCommonExecutor(): void
    {
        $this->assertTrue(is_a(Azure::class, CommonExecutor::class, true));
    }
}
