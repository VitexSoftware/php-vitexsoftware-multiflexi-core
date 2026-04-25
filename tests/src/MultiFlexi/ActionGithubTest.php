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

use MultiFlexi\Action\Github;
use MultiFlexi\Application;
use MultiFlexi\CommonAction;
use PHPUnit\Framework\TestCase;

final class ActionGithubTest extends TestCase
{
    public function testExtendsCommonAction(): void
    {
        $this->assertTrue(is_a(Github::class, CommonAction::class, true));
    }

    public function testName(): void
    {
        $this->assertIsString(Github::name());
        $this->assertNotEmpty(Github::name());
    }

    public function testDescription(): void
    {
        $this->assertIsString(Github::description());
        $this->assertNotEmpty(Github::description());
    }

    public function testUsableForAppWithGithubHomepage(): void
    {
        $app = $this->createMock(Application::class);
        $app->method('getDataValue')->with('homepage')->willReturn('https://github.com/user/repo');
        $this->assertTrue(Github::usableForApp($app));
    }

    public function testNotUsableForAppWithoutGithubHomepage(): void
    {
        $app = $this->createMock(Application::class);
        $app->method('getDataValue')->with('homepage')->willReturn('https://example.com');
        $this->assertFalse(Github::usableForApp($app));
    }

    public function testInitialDataContainsToken(): void
    {
        $runtemplate = $this->createMock(\MultiFlexi\RunTemplate::class);
        $runtemplate->method('getMyKey')->willReturn(1);
        $runtemplate->method('getAppEnvironment')->willReturn([]);
        $action = new Github($runtemplate);
        $data = $action->initialData('');
        $this->assertArrayHasKey('token', $data);
    }
}
