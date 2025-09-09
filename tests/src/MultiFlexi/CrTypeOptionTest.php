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

use MultiFlexi\CrTypeOption;
use PHPUnit\Framework\TestCase;

final class CrTypeOptionTest extends TestCase
{
    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(CrTypeOption::class));
    }
}
