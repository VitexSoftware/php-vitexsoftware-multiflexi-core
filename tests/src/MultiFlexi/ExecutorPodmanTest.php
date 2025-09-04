<?php

declare(strict_types=1);

namespace Test\MultiFlexi;

use MultiFlexi\CommonExecutor;
use MultiFlexi\Executor\Podman;
use PHPUnit\Framework\TestCase;

final class ExecutorPodmanTest extends TestCase
{
    public function testExtendsCommonExecutor(): void
    {
        $this->assertTrue(is_a(Podman::class, CommonExecutor::class, true));
    }
}
