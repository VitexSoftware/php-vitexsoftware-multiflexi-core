<?php

declare(strict_types=1);

namespace Test\MultiFlexi;

use MultiFlexi\platformCompany;
use PHPUnit\Framework\TestCase;

final class platformCompanyTest extends TestCase
{
    public function testInterfaceExists(): void
    {
        $this->assertTrue(interface_exists(platformCompany::class));
    }

    public function testBehaviorPlaceholder(): void
    {
        $this->markTestIncomplete('Add platformCompany behavioral tests.');
    }
}
