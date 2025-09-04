<?php

declare(strict_types=1);

namespace Test\MultiFlexi;

use MultiFlexi\Env\Application;
use PHPUnit\Framework\TestCase;

final class EnvApplicationTest extends TestCase
{
    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(Application::class));
    }
}
