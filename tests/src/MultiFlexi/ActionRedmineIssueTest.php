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

use MultiFlexi\Action\RedmineIssue;
use MultiFlexi\Application;
use MultiFlexi\CommonAction;
use PHPUnit\Framework\TestCase;

final class ActionRedmineIssueTest extends TestCase
{
    public function testExtendsCommonAction(): void
    {
        $this->assertTrue(is_a(RedmineIssue::class, CommonAction::class, true));
    }

    public function testName(): void
    {
        $this->assertIsString(RedmineIssue::name());
        $this->assertNotEmpty(RedmineIssue::name());
    }

    public function testDescription(): void
    {
        $this->assertIsString(RedmineIssue::description());
        $this->assertNotEmpty(RedmineIssue::description());
    }

    public function testUsableForAnyAppObject(): void
    {
        $app = $this->createMock(Application::class);
        $this->assertTrue(RedmineIssue::usableForApp($app));
    }

    public function testInitialDataContainsRequiredKeys(): void
    {
        $runtemplate = $this->createMock(\MultiFlexi\RunTemplate::class);
        $runtemplate->method('getMyKey')->willReturn(1);
        $runtemplate->method('getAppEnvironment')->willReturn([]);
        $action = new RedmineIssue($runtemplate);
        $data = $action->initialData('');
        $this->assertArrayHasKey('token', $data);
        $this->assertArrayHasKey('url', $data);
        $this->assertArrayHasKey('project_id', $data);
    }
}
