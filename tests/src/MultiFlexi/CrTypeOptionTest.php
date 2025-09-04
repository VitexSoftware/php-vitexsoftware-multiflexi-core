<?php

declare(strict_types=1);

namespace Test\MultiFlexi;

use MultiFlexi\CrTypeOption;
use PHPUnit\Framework\TestCase;

final class CrTypeOptionTest extends TestCase
{
    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(CrTypeOption::class));
    }
}
