<?php

declare(strict_types=1);

namespace Test\MultiFlexi;

use MultiFlexi\Env\Company;
use PHPUnit\Framework\TestCase;

final class EnvCompanyTest extends TestCase
{
    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(Company::class));
    }
}
