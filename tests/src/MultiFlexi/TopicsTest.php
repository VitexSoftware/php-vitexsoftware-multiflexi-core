<?php

declare(strict_types=1);

namespace Test\MultiFlexi;

use MultiFlexi\Topics;
use PHPUnit\Framework\TestCase;

final class TopicsTest extends TestCase
{
    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(Topics::class));
    }

    public function testBehaviorPlaceholder(): void
    {
        $this->markTestIncomplete('Add Topics behavioral tests.');
    }
}
