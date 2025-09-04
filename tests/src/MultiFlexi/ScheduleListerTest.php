<?php

declare(strict_types=1);

namespace Test\MultiFlexi;

use MultiFlexi\ScheduleLister;
use PHPUnit\Framework\TestCase;

final class ScheduleListerTest extends TestCase
{
    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(ScheduleLister::class));
    }

    public function testBehaviorPlaceholder(): void
    {
        $this->markTestIncomplete('Add ScheduleLister behavioral tests.');
    }
}
