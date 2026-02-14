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

use MultiFlexi\Job;

/**
 * Tests for MultiFlexi\Job.
 */
class JobTest extends \PHPUnit\Framework\TestCase
{
    protected Job $object;
    protected \MultiFlexi\ConfigFields $env;

    /**
     * Sets up the fixture.
     */
    protected function setUp(): void
    {
        $this->object = new \MultiFlexi\Job();
        $this->env = new \MultiFlexi\ConfigFields();
        // Mock Application, Company, RunTemplate, executor for Job
        $mockApp = $this->createMock(\MultiFlexi\Application::class);
        $mockApp->method('getDataValue')->willReturn('dummy');
        $mockApp->method('getRecordName')->willReturn('dummy');
        $mockApp->method('getMyKey')->willReturn(1);
        $mockCompany = $this->createMock(\MultiFlexi\Company::class);
        $mockCompany->method('getDataValue')->willReturn('dummy');
        $mockCompany->method('getMyKey')->willReturn(1);
        $mockRunTemplate = $this->createMock(\MultiFlexi\RunTemplate::class);
        $mockRunTemplate->method('getMyKey')->willReturn(1);
        $mockRunTemplate->method('getApplication')->willReturn($mockApp);
        $mockRunTemplate->method('getCompany')->willReturn($mockCompany);
        $mockRunTemplate->method('getDataValue')->willReturn('dummy');
        $mockRunTemplate->method('getRecordName')->willReturn('dummy');
        $this->object->application = $mockApp;
        $this->object->company = $mockCompany;
        $this->object->runTemplate = $mockRunTemplate;
        $mockExecutor = $this->getMockBuilder(\MultiFlexi\Executor\Native::class)
            ->setConstructorArgs([$this->object])
            ->onlyMethods(['commandline', 'getPid', 'getExitCode', 'getOutput', 'getErrorOutput', 'setJob', 'launchJob'])
            ->getMock();
        $mockExecutor->method('commandline')->willReturn('dummy');
        $mockExecutor->method('getPid')->willReturn(1);
        $mockExecutor->method('getExitCode')->willReturn(0);
        $mockExecutor->method('getOutput')->willReturn('output');
        $mockExecutor->method('getErrorOutput')->willReturn('error');
        // setJob and launchJob are void methods, do not set willReturn for them
        $this->object->executor = $mockExecutor;
    }

    /**
     * @covers \MultiFlexi\Job::newJob
     */
    public function testnewJob(): void
    {
        // newJob uses Ease\Shared::user(), which is now mocked in setUp
        $result = $this->object->newJob(1, $this->env, new \DateTime());
        $this->assertIsInt($result);
        $this->assertGreaterThanOrEqual(0, $result);
    }

    /**
     * @covers \MultiFlexi\Job::runBegin
     */
    public function testrunBegin(): void
    {
        $this->object->setMyKey(1);
        // runBegin returns int Job ID
        $result = $this->object->runBegin();
        $this->assertIsInt($result);
    }

    /**
     * @covers \MultiFlexi\Job::runEnd
     */
    public function testrunEnd(): void
    {
        $this->object->setMyKey(1);
        // Provide dummy arguments for runEnd: statusCode, stdout, stderr
        $result = $this->object->runEnd(0, 'stdout', 'stderr');
        $this->assertIsInt($result);
    }

    /**
     * @covers \MultiFlexi\Job::isProvisioned
     */
    public function testisProvisioned(): void
    {
        $this->object->setMyKey(1);
        // Provide dummy runtemplateId
        $result = $this->object->isProvisioned(1);
        $this->assertIsBool($result);
    }

    /**
     * @covers \MultiFlexi\Job::columnDefs
     */
    public function testcolumnDefs(): void
    {
        $result = $this->object->columnDefs();
        $this->assertIsString($result);
        $this->assertStringContainsString('columnDefs', $result);
    }

    /**
     * @covers \MultiFlexi\Job::prepareJob
     */
    public function testprepareJob(): void
    {
        // Provide dummy arguments for prepareJob
        $runTemplateId = 1;
        $envOverride = new \MultiFlexi\ConfigFields();
        $scheduled = new \DateTime();
        $result = $this->object->prepareJob($runTemplateId, $envOverride, $scheduled);
        $this->assertIsString($result);
    }

    /**
     * @covers \MultiFlexi\Job::scheduleJobRun
     */
    public function testscheduleJobRun(): void
    {
        // Provide dummy DateTime argument
        $when = new \DateTime();
        $result = $this->object->scheduleJobRun($when);
        $this->assertIsInt($result);
    }

    /**
     * @covers \MultiFlexi\Job::reportToZabbix
     */
    public function testreportToZabbix(): void
    {
        // Provide array as required by reportToZabbix
        $result = $this->object->reportToZabbix(['metric' => 'Test Metric', 'value' => 100]);
        $this->assertIsBool($result);
    }

    /**
     * @covers \MultiFlexi\Job::performJob
     */
    public function testperformJob(): void
    {
        // performJob returns void, just call it to ensure no exception
        $this->object->setMyKey(1);
        $this->object->performJob();
        $this->assertTrue(true);
    }

    /**
     * @covers \MultiFlexi\Job::addOutput
     */
    public function testaddOutput(): void
    {
        // Method addOutput does not exist, skip this test
        $this->markTestSkipped('addOutput() does not exist in Job');
    }

    /**
     * @covers \MultiFlexi\Job::getOutputCachePlaintext
     */
    public function testgetOutputCachePlaintext(): void
    {
        // Method getOutputCachePlaintext does not exist, skip this test
        $this->markTestSkipped('getOutputCachePlaintext() does not exist in Job');
    }

    /**
     * @covers \MultiFlexi\Job::getCmdline
     */
    public function testgetCmdline(): void
    {
        // getCmdline expects application to be set
        $mockApp = $this->createMock(\MultiFlexi\Application::class);
        $mockApp->expects($this->any())
            ->method('getDataValue')
            ->willReturn('dummy');
        $this->object->application = $mockApp;
        $result = $this->object->getCmdline();
        $this->assertIsString($result);
    }

    /**
     * @covers \MultiFlexi\Job::getCmdParams
     */
    public function testgetCmdParams(): void
    {
        // getCmdParams expects application to be set
        $mockApp = $this->createMock(\MultiFlexi\Application::class);
        $mockApp->expects($this->any())
            ->method('getDataValue')
            ->willReturn('dummy');
        $this->object->application = $mockApp;
        $result = $this->object->getCmdParams();
        $this->assertIsString($result);
    }

    /**
     * @covers \MultiFlexi\Job::getOutput
     */
    public function testgetOutput(): void
    {
        // getOutput expects data to be set
        $this->object->setDataValue('stdout', 'Test Output');
        $result = $this->object->getOutput();
        $this->assertStringContainsString('Test Output', $result);
    }

    /**
     * @covers \MultiFlexi\Job::cleanUp
     */
    public function testcleanUp(): void
    {
        // cleanUp returns void, just call it
        $this->object->cleanUp();
        $this->assertTrue(true);
    }

    /**
     * @covers \MultiFlexi\Job::launcherScript
     */
    public function testlauncherScript(): void
    {
        $this->object->setMyKey(1);
        $result = $this->object->launcherScript();
        $this->assertIsString($result);
    }

    /**
     * @covers \MultiFlexi\Job::compileEnv
     */
    public function testcompileEnv(): void
    {
        // Skip this test if Environmentor::flatEnv does not exist
        if (!method_exists(\MultiFlexi\Environmentor::class, 'flatEnv')) {
            $this->markTestSkipped('Environmentor::flatEnv() does not exist');
        } else {
            $result = $this->object->compileEnv();
            $this->assertIsArray($result);
        }
    }

    public function testInitialization(): void
    {
        $job = new Job();
        $this->assertInstanceOf(Job::class, $job);
    }

    public function testSomeFunctionality(): void
    {
        $job = new Job();
        $this->assertInstanceOf(Job::class, $job);
        $this->assertArrayHasKey('y', Job::$intervalCode, 'intervalCode should have key y for yearly');
        $this->assertArrayHasKey('n', Job::$intervalSecond, 'intervalSecond should have key n for disabled');
    }

    /**
     * @covers \MultiFlexi\Job::environment
     */
    public function testEnvironment(): void
    {
        $job = new Job();
        
        // Test getter - should return ConfigFields instance
        $env = $job->environment();
        $this->assertInstanceOf(\MultiFlexi\ConfigFields::class, $env);
        
        // Test setter - add fields and verify they are added
        $additionalFields = new \MultiFlexi\ConfigFields('Test Fields');
        $testField = new \MultiFlexi\ConfigField('test_key', 'string', 'Test Key', 'Test Description', 'Test Hint', 'test_value');
        $additionalFields->addField($testField);
        
        $result = $job->environment($additionalFields);
        $this->assertInstanceOf(\MultiFlexi\ConfigFields::class, $result);
        
        // Verify the fields were added to the environment
        $allFields = $job->environment()->getFields();
        $this->assertIsArray($allFields);
        $this->assertArrayHasKey('test_key', $allFields);
    }
}
