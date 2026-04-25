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

use MultiFlexi\Action\TriggerJenkins;
use MultiFlexi\Application;
use MultiFlexi\CommonAction;
use PHPUnit\Framework\TestCase;

final class ActionTriggerJenkinsTest extends TestCase
{
    public function testExtendsCommonAction(): void
    {
        $this->assertTrue(is_a(TriggerJenkins::class, CommonAction::class, true));
    }

    public function testName(): void
    {
        $this->assertIsString(TriggerJenkins::name());
        $this->assertNotEmpty(TriggerJenkins::name());
    }

    public function testDescription(): void
    {
        $this->assertIsString(TriggerJenkins::description());
        $this->assertNotEmpty(TriggerJenkins::description());
    }

    public function testUsableForAnyApp(): void
    {
        $app = $this->createMock(Application::class);
        $this->assertTrue(TriggerJenkins::usableForApp($app));
    }

    public function testInitialDataContainsRequiredKeys(): void
    {
        $runtemplate = $this->createMock(\MultiFlexi\RunTemplate::class);
        $runtemplate->method('getMyKey')->willReturn(1);
        $runtemplate->method('getAppEnvironment')->willReturn([]);
        $action = new TriggerJenkins($runtemplate);
        $data = $action->initialData('');
        $this->assertArrayHasKey('url', $data);
        $this->assertArrayHasKey('job_name', $data);
        $this->assertArrayHasKey('user', $data);
        $this->assertArrayHasKey('api_token', $data);
        $this->assertArrayHasKey('token', $data);
    }

    public function testLogoIsBase64Svg(): void
    {
        $logo = TriggerJenkins::logo();
        $this->assertStringStartsWith('data:image/svg+xml;base64,', $logo);
    }
}
