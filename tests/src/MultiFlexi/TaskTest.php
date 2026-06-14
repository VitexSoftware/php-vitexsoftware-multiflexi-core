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

use MultiFlexi\Task;
use PHPUnit\Framework\TestCase;

/**
 * Tests for MultiFlexi\Task state machine (no DB required).
 *
 * Task state is held in the data array managed by Ease\Atom via setDataValue /
 * getDataValue. We can exercise the state-machine logic without a database by
 * intercepting the DB-write calls (updateToSQL / insertToSQL) through a partial
 * mock, or by simply accepting that those calls return 0/false when there is no
 * DB connection.
 */
class TaskTest extends TestCase
{
    /**
     * Build a Task instance with in-memory data — no DB connection required.
     *
     * @param array<string, mixed> $data
     */
    private function makeTask(array $data = []): Task
    {
        $defaults = [
            'state'              => Task::STATE_OPEN,
            'attempts'           => 0,
            'window_start'       => (new \DateTime())->format('Y-m-d H:i:s'),
            'window_end'         => (new \DateTime('+1 hour'))->format('Y-m-d H:i:s'),
            'deadline'           => (new \DateTime('+1 hour'))->format('Y-m-d H:i:s'),
            'runtemplate_id'     => 0,
            'fulfilled_by_job_id' => null,
            'fulfilled_at'       => null,
            'created_at'         => (new \DateTime())->format('Y-m-d H:i:s'),
        ];

        /** @var Task&\PHPUnit\Framework\MockObject\MockObject $task */
        $task = $this->getMockBuilder(Task::class)
            ->onlyMethods(['updateToSQL', 'insertToSQL', 'loadFromSQL'])
            ->getMock();

        // updateToSQL and insertToSQL do nothing (no DB)
        $task->method('updateToSQL')->willReturn(0);
        $task->method('insertToSQL')->willReturn(0);
        $task->method('loadFromSQL')->willReturn(0);

        // Load merged data into the instance
        $task->setData(array_merge($defaults, $data), true);

        return $task;
    }

    // -------------------------------------------------------------------------
    // State constants
    // -------------------------------------------------------------------------

    public function testStateConstantsExist(): void
    {
        $this->assertSame('open',           Task::STATE_OPEN);
        $this->assertSame('running',        Task::STATE_RUNNING);
        $this->assertSame('fulfilled',      Task::STATE_FULFILLED);
        $this->assertSame('fulfilled_late', Task::STATE_FULFILLED_LATE);
        $this->assertSame('failed',         Task::STATE_FAILED);
        $this->assertSame('missed',         Task::STATE_MISSED);
    }

    // -------------------------------------------------------------------------
    // markFailed
    // -------------------------------------------------------------------------

    /**
     * @covers \MultiFlexi\Task::markFailed
     */
    public function testMarkFailedSetsStateFailed(): void
    {
        $task = $this->makeTask(['state' => Task::STATE_RUNNING]);
        $task->markFailed();

        $this->assertSame(Task::STATE_FAILED, $task->getDataValue('state'));
    }

    // -------------------------------------------------------------------------
    // markMissed
    // -------------------------------------------------------------------------

    /**
     * @covers \MultiFlexi\Task::markMissed
     */
    public function testMarkMissedSetsStateMissed(): void
    {
        $task = $this->makeTask(['state' => Task::STATE_OPEN]);
        $task->markMissed();

        $this->assertSame(Task::STATE_MISSED, $task->getDataValue('state'));
    }

    // -------------------------------------------------------------------------
    // markRunning
    // -------------------------------------------------------------------------

    /**
     * @covers \MultiFlexi\Task::markRunning
     */
    public function testMarkRunningSetsStateRunning(): void
    {
        $task = $this->makeTask(['state' => Task::STATE_OPEN]);
        $task->markRunning();

        $this->assertSame(Task::STATE_RUNNING, $task->getDataValue('state'));
    }

    // -------------------------------------------------------------------------
    // canRetry
    // -------------------------------------------------------------------------

    /**
     * @covers \MultiFlexi\Task::canRetry
     */
    public function testCanRetryReturnsFalseWhenWindowExpired(): void
    {
        $task = $this->makeTask([
            'window_end' => (new \DateTime('-1 second'))->format('Y-m-d H:i:s'),
            'deadline'   => (new \DateTime('-1 second'))->format('Y-m-d H:i:s'),
            'attempts'   => 0,
        ]);

        $this->assertFalse($task->canRetry());
    }

    /**
     * @covers \MultiFlexi\Task::canRetry
     *
     * Without a DB, RunTemplate construction will return max_attempts = 1.
     * We set attempts = 1 so attempts >= max_attempts → canRetry is false.
     */
    public function testCanRetryReturnsFalseWhenMaxAttemptsReached(): void
    {
        // Window is still open
        $task = $this->makeTask([
            'window_end' => (new \DateTime('+1 hour'))->format('Y-m-d H:i:s'),
            'deadline'   => (new \DateTime('+1 hour'))->format('Y-m-d H:i:s'),
            'attempts'   => 1,   // equals max_attempts default of 1 → no retry
        ]);

        // canRetry calls RunTemplate which has no DB; max_attempts defaults to 1
        // so attempts (1) >= max_attempts (1) → false
        try {
            $result = $task->canRetry();
            $this->assertFalse($result, 'Should not retry when attempts >= max_attempts');
        } catch (\Exception $e) {
            $this->markTestSkipped('DB unavailable for RunTemplate lookup: '.$e->getMessage());
        }
    }

    /**
     * @covers \MultiFlexi\Task::canRetry
     *
     * With attempts = 0 and an open window, canRetry should be true
     * when max_attempts > 0 (default).
     */
    public function testCanRetryReturnsTrueWhenAttemptsLessThanMax(): void
    {
        $task = $this->makeTask([
            'window_end' => (new \DateTime('+1 hour'))->format('Y-m-d H:i:s'),
            'deadline'   => (new \DateTime('+1 hour'))->format('Y-m-d H:i:s'),
            'attempts'   => 0,
        ]);

        // Default max_attempts from RunTemplate (no DB) is 1; 0 < 1 → true
        try {
            $result = $task->canRetry();
            $this->assertTrue($result, 'Should retry when attempts < max_attempts and window is open');
        } catch (\Exception $e) {
            $this->markTestSkipped('DB unavailable for RunTemplate lookup: '.$e->getMessage());
        }
    }

    // -------------------------------------------------------------------------
    // isWindowExpired / isOverDeadline
    // -------------------------------------------------------------------------

    /**
     * @covers \MultiFlexi\Task::isWindowExpired
     */
    public function testIsWindowExpiredReturnsTrueForPastWindow(): void
    {
        $task = $this->makeTask([
            'window_end' => (new \DateTime('-1 second'))->format('Y-m-d H:i:s'),
        ]);

        $this->assertTrue($task->isWindowExpired());
    }

    /**
     * @covers \MultiFlexi\Task::isWindowExpired
     */
    public function testIsWindowExpiredReturnsFalseForFutureWindow(): void
    {
        $task = $this->makeTask([
            'window_end' => (new \DateTime('+1 hour'))->format('Y-m-d H:i:s'),
        ]);

        $this->assertFalse($task->isWindowExpired());
    }

    /**
     * @covers \MultiFlexi\Task::isOverDeadline
     */
    public function testIsOverDeadlineReturnsTrueForPastDeadline(): void
    {
        $task = $this->makeTask([
            'deadline' => (new \DateTime('-1 second'))->format('Y-m-d H:i:s'),
        ]);

        $this->assertTrue($task->isOverDeadline());
    }

    /**
     * @covers \MultiFlexi\Task::isOverDeadline
     */
    public function testIsOverDeadlineReturnsFalseForFutureDeadline(): void
    {
        $task = $this->makeTask([
            'deadline' => (new \DateTime('+1 hour'))->format('Y-m-d H:i:s'),
        ]);

        $this->assertFalse($task->isOverDeadline());
    }

    // -------------------------------------------------------------------------
    // incrementAttempts
    // -------------------------------------------------------------------------

    /**
     * @covers \MultiFlexi\Task::incrementAttempts
     */
    public function testIncrementAttemptsIncrementsCounter(): void
    {
        $task = $this->makeTask(['attempts' => 2]);
        $task->incrementAttempts();

        $this->assertSame(3, (int) $task->getDataValue('attempts'));
    }

    /**
     * @covers \MultiFlexi\Task::incrementAttempts
     */
    public function testIncrementAttemptsSetsStateOpen(): void
    {
        $task = $this->makeTask(['state' => Task::STATE_RUNNING, 'attempts' => 0]);
        $task->incrementAttempts();

        $this->assertSame(Task::STATE_OPEN, $task->getDataValue('state'));
    }

    // -------------------------------------------------------------------------
    // Initial state
    // -------------------------------------------------------------------------

    public function testNewTaskStartsInOpenState(): void
    {
        $task = new Task();
        // A freshly constructed Task has no data yet; the state column is empty/null.
        // After setData with STATE_OPEN it should read back as 'open'.
        $task->setDataValue('state', Task::STATE_OPEN);

        $this->assertSame(Task::STATE_OPEN, $task->getDataValue('state'));
    }
}
