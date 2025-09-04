<?php

declare(strict_types=1);

namespace Test\MultiFlexi;

use MultiFlexi\TopicManger;
use PHPUnit\Framework\TestCase;

final class TopicMangerTest extends TestCase
{
    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(TopicManger::class));
    }

    public function testBehaviorPlaceholder(): void
    {
        $this->markTestIncomplete('Add TopicManger behavioral tests.');
    }
}
