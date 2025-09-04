<?php

declare(strict_types=1);

namespace Test\MultiFlexi;

use MultiFlexi\Topic;
use PHPUnit\Framework\TestCase;

final class TopicTest extends TestCase
{
    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(Topic::class));
    }

    public function testBehaviorPlaceholder(): void
    {
        $this->markTestIncomplete('Add Topic behavioral tests.');
    }
}
