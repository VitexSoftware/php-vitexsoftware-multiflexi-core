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

use Envms\FluentPDO\Queries\Select;
use MultiFlexi\Job;
use MultiFlexi\RunTemplate;
use MultiFlexi\Scheduler;
use PHPUnit\Framework\TestCase;

/**
 * Regression coverage for the race condition where a schedule row created by
 * addJob() could be observed (and deleted as "referencing missing job") by a
 * concurrent purge before the runtemplate's next_schedule update and the
 * schedule insert became visible together.
 *
 * @covers \MultiFlexi\Scheduler::addJob
 */
class SchedulerAddJobAtomicityTest extends TestCase
{
    public function testAddJobWrapsNextScheduleUpdateAndInsertInOwnTransaction(): void
    {
        $runTemplate = $this->createMock(RunTemplate::class);
        $runTemplate->method('getMyKey')->willReturn(11);
        $runTemplate->expects($this->once())
            ->method('updateToSQL')
            ->with(['next_schedule' => '2026-01-01 00:00:00'], ['id' => 11]);

        $job = $this->createMock(Job::class);
        $job->method('getMyKey')->willReturn(77);
        $job->method('getDataValue')->with('schedule_type')->willReturn('cron');
        $job->method('getRuntemplate')->willReturn($runTemplate);

        $noExistingSchedule = $this->createMock(Select::class);
        $noExistingSchedule->method('where')->willReturnSelf();
        $noExistingSchedule->method('fetch')->willReturn(false);

        $pdo = $this->createMock(\PDO::class);
        $pdo->expects($this->once())->method('inTransaction')->willReturn(false);
        $pdo->expects($this->once())->method('beginTransaction');
        $pdo->expects($this->once())->method('commit');
        $pdo->expects($this->never())->method('rollBack');

        $scheduler = $this->getMockBuilder(Scheduler::class)
            ->onlyMethods(['getPdo', 'listingQuery', 'insertToSQL'])
            ->getMock();
        $scheduler->method('getPdo')->willReturn($pdo);
        $scheduler->method('listingQuery')->willReturn($noExistingSchedule);
        $scheduler->expects($this->once())
            ->method('insertToSQL')
            ->with(['after' => '2026-01-01 00:00:00', 'job' => 77])
            ->willReturn(501);

        $result = $scheduler->addJob($job, new \DateTime('2026-01-01 00:00:00'));

        $this->assertSame(501, $result);
    }

    public function testAddJobRollsBackOwnedTransactionOnInsertFailure(): void
    {
        $runTemplate = $this->createMock(RunTemplate::class);
        $runTemplate->method('getMyKey')->willReturn(11);
        $runTemplate->method('updateToSQL');

        $job = $this->createMock(Job::class);
        $job->method('getMyKey')->willReturn(77);
        $job->method('getDataValue')->with('schedule_type')->willReturn('cron');
        $job->method('getRuntemplate')->willReturn($runTemplate);

        $noExistingSchedule = $this->createMock(Select::class);
        $noExistingSchedule->method('where')->willReturnSelf();
        $noExistingSchedule->method('fetch')->willReturn(false);

        $pdo = $this->createMock(\PDO::class);
        $pdo->method('inTransaction')->willReturnOnConsecutiveCalls(false, true);
        $pdo->expects($this->once())->method('beginTransaction');
        $pdo->expects($this->never())->method('commit');
        $pdo->expects($this->once())->method('rollBack');

        $scheduler = $this->getMockBuilder(Scheduler::class)
            ->onlyMethods(['getPdo', 'listingQuery', 'insertToSQL'])
            ->getMock();
        $scheduler->method('getPdo')->willReturn($pdo);
        $scheduler->method('listingQuery')->willReturn($noExistingSchedule);
        $scheduler->method('insertToSQL')->willThrowException(new \RuntimeException('boom'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('boom');

        $scheduler->addJob($job, new \DateTime('2026-01-01 00:00:00'));
    }

    public function testAddJobJoinsCallersTransactionWithoutCommittingOrRollingBackIt(): void
    {
        $runTemplate = $this->createMock(RunTemplate::class);
        $runTemplate->method('getMyKey')->willReturn(11);
        $runTemplate->method('updateToSQL');

        $job = $this->createMock(Job::class);
        $job->method('getMyKey')->willReturn(77);
        $job->method('getDataValue')->with('schedule_type')->willReturn('cron');
        $job->method('getRuntemplate')->willReturn($runTemplate);

        $noExistingSchedule = $this->createMock(Select::class);
        $noExistingSchedule->method('where')->willReturnSelf();
        $noExistingSchedule->method('fetch')->willReturn(false);

        $pdo = $this->createMock(\PDO::class);
        $pdo->expects($this->once())->method('inTransaction')->willReturn(true);
        $pdo->expects($this->never())->method('beginTransaction');
        $pdo->expects($this->never())->method('commit');
        $pdo->expects($this->never())->method('rollBack');

        $scheduler = $this->getMockBuilder(Scheduler::class)
            ->onlyMethods(['getPdo', 'listingQuery', 'insertToSQL'])
            ->getMock();
        $scheduler->method('getPdo')->willReturn($pdo);
        $scheduler->method('listingQuery')->willReturn($noExistingSchedule);
        $scheduler->method('insertToSQL')->willReturn(502);

        $result = $scheduler->addJob($job, new \DateTime('2026-01-01 00:00:00'));

        $this->assertSame(502, $result);
    }
}
