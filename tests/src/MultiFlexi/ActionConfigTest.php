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

use MultiFlexi\ActionConfig;

/**
 * Generated by PHPUnit_SkeletonGenerator on 2024-11-07 at 12:16:27.
 */
class ActionConfigTest extends \PHPUnit\Framework\TestCase
{
    protected ActionConfig $object;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp(): void
    {
        $this->object = new ActionConfig();
        $this->object->insertToSQL(['id' => 1, 'name' => 'TestTemplate']);
    }

    /**
     * Tears down the fixture, for example, closes a network connection.
     * This method is called after a test is executed.
     */
    protected function tearDown(): void
    {
    }

    /**
     * @covers \MultiFlexi\ActionConfig::saveModeConfigs
     */
    public function testsaveModeConfigs(): void
    {
        $mode = 'success';
        $values = [
            'module1' => [
                'key1' => 'value1',
                'key2' => 'value2',
            ],
            'module2' => [
                'key3' => 'value3',
            ],
        ];
        $runtemplate = 1;

        $this->object->saveModeConfigs($mode, $values, $runtemplate);

        // Add assertions to verify the expected behavior
        $this->assertTrue(true); // Placeholder assertion
    }

    /**
     * @covers \MultiFlexi\ActionConfig::saveActionFields
     */
    public function testsaveActionFields(): void
    {
        $module = 'module1';
        $mode = 'success';
        $runtemplate = 1;
        $configs = [
            'key1' => 'value1',
            'key2' => 'value2',
        ];

        $this->object->saveActionFields($module, $mode, $runtemplate, $configs);

        // Add assertions to verify the expected behavior
        $this->assertTrue(true); // Placeholder assertion
    }

    /**
     * @covers \MultiFlexi\ActionConfig::saveActionConfig
     */
    public function testsaveActionConfig(): void
    {
        $module = 'module1';
        $key = 'key1';
        $value = 'value1';
        $mode = 'success';
        $runtemplate = 1;

        $this->object->saveActionConfig($module, $key, $value, $mode, $runtemplate);

        // Add assertions to verify the expected behavior
        $this->assertTrue(true); // Placeholder assertion
    }

    /**
     * @covers \MultiFlexi\ActionConfig::getModuleConfig
     */
    public function testgetModuleConfig(): void
    {
        $module = 'module1';
        $key = 'key1';
        $value = 'value1';
        $mode = 'success';
        $runtemplate = 1;

        $result = $this->object->getModuleConfig($module, $key, $value, $mode, $runtemplate);

        // Add assertions to verify the expected behavior
        $this->assertNotEmpty($result); // Placeholder assertion
    }

    /**
     * @covers \MultiFlexi\ActionConfig::getRuntemplateConfig
     */
    public function testgetRuntemplateConfig(): void
    {
        $runtemplateId = 1;

        $result = $this->object->getRuntemplateConfig($runtemplateId);

        // Add assertions to verify the expected behavior
        $this->assertNotEmpty($result); // Placeholder assertion
    }
}
