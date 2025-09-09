<?php

declare(strict_types=1);

/**
 * This file is part of the MultiFlexi package
 *
 * https://multiflexi.eu/
 *
 * (c) Vítězslav Dvořák <http://vitexsoftware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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
