<?php

declare(strict_types=1);

namespace Test\MultiFlexi;

use MultiFlexi\CommonExecutor;
use MultiFlexi\Executor\Docker;
use PHPUnit\Framework\TestCase;

final class ExecutorDockerTest extends TestCase
{
    public function testExtendsCommonExecutor(): void
    {
        $this->assertTrue(is_a(Docker::class, CommonExecutor::class, true));
    }
}
