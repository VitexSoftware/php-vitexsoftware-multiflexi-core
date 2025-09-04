<?php

declare(strict_types=1);

namespace Test\MultiFlexi;

use MultiFlexi\Requirement;
use PHPUnit\Framework\TestCase;

final class RequirementTest extends TestCase
{
    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(Requirement::class));
    }

    public function testBehaviorPlaceholder(): void
    {
        $this->markTestIncomplete('Add Requirement behavioral tests.');
    }
}
