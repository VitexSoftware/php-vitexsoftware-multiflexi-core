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

use MultiFlexi\BitwardenServiceDelegate;
use PHPUnit\Framework\TestCase;

final class BitwardenServiceDelegateTest extends TestCase
{
    public function testClassExistsOrSkip(): void
    {
        if (!interface_exists('Jalismrs\\Bitwarden\\BitwardenServiceDelegate')) {
            $this->markTestSkipped('External dependency Jalismrs\\Bitwarden is not installed.');

            return;
        }

        $this->assertTrue(class_exists(BitwardenServiceDelegate::class));
    }
}
