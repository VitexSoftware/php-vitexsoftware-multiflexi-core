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

use MultiFlexi\RunTemplate;
use PHPUnit\Framework\TestCase;

/**
 * DB-independent tests for RunTemplate config pruning helpers.
 */
final class RunTemplateConfigPruneTest extends TestCase
{
    /**
     * @covers \MultiFlexi\RunTemplate::intersectConfigNames
     */
    public function testintersectConfigNames(): void
    {
        $stored = ['A' => '1', 'B' => '2', 'C' => '3'];

        // Overlap: only names provided by the credential are returned, values kept.
        $this->assertSame(
            ['A' => '1', 'C' => '3'],
            RunTemplate::intersectConfigNames($stored, ['A', 'C', 'D']),
        );

        // No overlap.
        $this->assertSame([], RunTemplate::intersectConfigNames($stored, ['X', 'Y']));

        // Empty provided list.
        $this->assertSame([], RunTemplate::intersectConfigNames($stored, []));

        // Empty stored config.
        $this->assertSame([], RunTemplate::intersectConfigNames([], ['A']));
    }
}
