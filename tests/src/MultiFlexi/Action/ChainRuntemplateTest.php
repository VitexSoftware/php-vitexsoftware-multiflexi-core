<?php

declare(strict_types=1);

/**
 * PHPUnit test for ChainRuntemplate Action
 *
 * @author vitex
 */
namespace MultiFlexi\Tests\Action;

use PHPUnit\Framework\TestCase;
use MultiFlexi\Action\ChainRuntemplate;
use MultiFlexi\Job;
use MultiFlexi\RunTemplate;
use MultiFlexi\ConfigFields;

class ChainRuntemplateTest extends TestCase
{
    /**
     * Test scheduling chained RunTemplate
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
     * Test perform with missing RunTemplate
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
