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

namespace MultiFlexi;

use PHPUnit\Framework\TestCase;

/**
 * ToDo Test Class.
 *
 * @author vitex
 */
class ToDoTest extends TestCase
{
    private ToDo $object;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp(): void
    {
        $this->object = new ToDo();
    }

    /**
     * Tears down the fixture, for example, closes a network connection.
     * This method is called after a test is executed.
     */
    protected function tearDown(): void
    {
        $this->object = null;
    }

    /**
     * Test class initialization.
     */
    public function testInitialization(): void
    {
        $this->assertInstanceOf(ToDo::class, $this->object);
        $this->assertEquals('todos', $this->object->getMyTable());
        $this->assertEquals('title', $this->object->nameColumn);
    }

    /**
     * Test createToDo method.
     */
    public function testCreateToDo(): void
    {
        // Mock the insertToSQL method to return a mock ID
        $mockToDo = $this->getMockBuilder(ToDo::class)
            ->onlyMethods(['insertToSQL', 'getMyKey'])
            ->getMock();

        $mockToDo->expects($this->once())
            ->method('insertToSQL')
            ->willReturn(123);

        $result = $mockToDo->createToDo(
            'Test ToDo',
            'Test Description',
            1,
            1,
            1,
            'high',
            'open',
        );

        $this->assertEquals(123, $result);
    }

    /**
     * Test complete method.
     */
    public function testComplete(): void
    {
        $mockToDo = $this->getMockBuilder(ToDo::class)
            ->onlyMethods(['updateToSQL'])
            ->getMock();

        $mockToDo->expects($this->once())
            ->method('updateToSQL')
            ->willReturn(1);

        $result = $mockToDo->complete();
        $this->assertTrue($result);
    }

    /**
     * Test getRecordName method.
     */
    public function testGetRecordName(): void
    {
        $mockToDo = $this->getMockBuilder(ToDo::class)
            ->onlyMethods(['getDataValue', 'getMyKey'])
            ->getMock();

        $mockToDo->expects($this->once())
            ->method('getDataValue')
            ->with('title')
            ->willReturn('Test Title');

        $result = $mockToDo->getRecordName();
        $this->assertEquals('Test Title', $result);
    }

    /**
     * Test getRecordName with no title.
     */
    public function testGetRecordNameWithoutTitle(): void
    {
        $mockToDo = $this->getMockBuilder(ToDo::class)
            ->onlyMethods(['getDataValue', 'getMyKey'])
            ->getMock();

        $mockToDo->expects($this->once())
            ->method('getDataValue')
            ->with('title')
            ->willReturn('');

        $mockToDo->expects($this->once())
            ->method('getMyKey')
            ->willReturn(42);

        $result = $mockToDo->getRecordName();
        $this->assertStringContainsString('42', $result);
    }

    /**
     * Test getColumns method returns proper structure.
     */
    public function testGetColumns(): void
    {
        $columns = $this->object->getColumns();

        $this->assertIsArray($columns);
        $this->assertArrayHasKey('id', $columns);
        $this->assertArrayHasKey('title', $columns);
        $this->assertArrayHasKey('description', $columns);
        $this->assertArrayHasKey('job_id', $columns);
        $this->assertArrayHasKey('priority', $columns);
        $this->assertArrayHasKey('status', $columns);

        // Check title column is marked as required
        $this->assertTrue($columns['title']['required']);
    }

    /**
     * Test getToDosForCompany method.
     */
    public function testGetToDosForCompany(): void
    {
        // This test requires database mocking which is complex
        // Mark as incomplete for now
        $this->markTestIncomplete('This test requires proper database mocking to be implemented.');
    }

    /**
     * Test getToDosForJob method.
     */
    public function testGetToDosForJob(): void
    {
        // This test requires database mocking which is complex
        // Mark as incomplete for now
        $this->markTestIncomplete('This test requires proper database mocking to be implemented.');
    }
}
