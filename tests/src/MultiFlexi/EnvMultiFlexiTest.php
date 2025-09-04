<?php

declare(strict_types=1);

namespace Test\MultiFlexi;

use MultiFlexi\Env\MultiFlexi;
use PHPUnit\Framework\TestCase;

final class EnvMultiFlexiTest extends TestCase
{
    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(MultiFlexi::class));
    }
}
