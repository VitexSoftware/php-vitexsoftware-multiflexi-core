<?php

declare(strict_types=1);

namespace Test\MultiFlexi;

use MultiFlexi\Env\EaseLogger;
use PHPUnit\Framework\TestCase;

final class EnvEaseLoggerTest extends TestCase
{
    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(EaseLogger::class));
    }
}
