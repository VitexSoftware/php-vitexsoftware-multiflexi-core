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

namespace MultiFlexi\Tests\Action;

use MultiFlexi\Action\ChainRuntemplate;
use MultiFlexi\ConfigFields;
use MultiFlexi\Job;
use MultiFlexi\RunTemplate;
use PHPUnit\Framework\TestCase;

class ChainRuntemplateTest extends TestCase
{
    /**
     * Test scheduling chained RunTemplate.
     */
    public function testPerformSchedulesChainedRuntemplate(): void
    {
        $mockJob = $this->createMock(Job::class);
        $mockJob->environment = new ConfigFields('Job Env');
        $mockJob->method('getDataValue')->willReturn('Native');

        $mockRunTemplate = $this->createMock(RunTemplate::class);
        $mockRunTemplate->method('getMyKey')->willReturn(123);

        $action = new ChainRuntemplate($mockRunTemplate, ['RunTemplate' => ['rtid' => 123]]);

        // Should not throw exception
        $action->perform($mockJob);
        $this->assertTrue(true);
    }

    /**
     * Test perform with missing RunTemplate.
     */
    public function testPerformWithMissingRuntemplate(): void
    {
        $mockJob = $this->createMock(Job::class);
        $mockJob->environment = new ConfigFields('Job Env');
        $mockJob->method('getDataValue')->willReturn('Native');

        $mockRunTemplate = $this->createMock(RunTemplate::class);
        $mockRunTemplate->method('getMyKey')->willReturn(null);

        $action = new ChainRuntemplate($mockRunTemplate, ['RunTemplate' => ['rtid' => null]]);

        // Should not throw exception
        $action->perform($mockJob);
        $this->assertTrue(true);
    }
}
