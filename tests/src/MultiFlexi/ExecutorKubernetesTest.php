<?php

declare(strict_types=1);

namespace Test\MultiFlexi;

use MultiFlexi\CommonExecutor;
use MultiFlexi\Executor\Kubernetes;
use PHPUnit\Framework\TestCase;

final class ExecutorKubernetesTest extends TestCase
{
    public function testExtendsCommonExecutor(): void
    {
        $this->assertTrue(is_a(Kubernetes::class, CommonExecutor::class, true));
    }
}
