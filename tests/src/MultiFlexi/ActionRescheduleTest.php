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

use MultiFlexi\Action\Reschedule;
use MultiFlexi\CommonAction;
use PHPUnit\Framework\TestCase;

final class ActionRescheduleTest extends TestCase
{
    public function testExtendsCommonAction(): void
    {
        $this->assertTrue(is_a(Reschedule::class, CommonAction::class, true));
    }
}
