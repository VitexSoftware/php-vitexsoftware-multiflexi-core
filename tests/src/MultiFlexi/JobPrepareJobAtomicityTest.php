<?php

declare(strict_types=1);

namespace Test\MultiFlexi;

use MultiFlexi\Application;
use MultiFlexi\Company;
use MultiFlexi\ConfigFields;
use MultiFlexi\Job;
use MultiFlexi\RunTemplate;
use PHPUnit\Framework\TestCase;

/**
 * @covers \MultiFlexi\Job::prepareJob
 */
class JobPrepareJobAtomicityTest extends TestCase
{
    public function testPrepareJobRemovesCreatedJobWhenPreparationFails(): void
    {
        $application = $this->createMock(Application::class);
        $company = $this->createMock(Company::class);

        $runTemplate = $this->createMock(RunTemplate::class);
        $runTemplate->method('getApplication')->willReturn($application);
        $runTemplate->method('getCompany')->willReturn($company);
        $runTemplate->method('getMyKey')->willReturn(11);
        $runTemplate->method('getDataValue')->willReturnCallback(static function (string $column) {
            return match ($column) {
                'app_id' => 21,
                'company_id' => 31,
                'next_schedule' => '2026-01-01 00:00:00',
                default => null,
            };
        });
        $runTemplate->expects($this->once())
            ->method('updateToSQL')
            ->with(['next_schedule' => '2026-01-01 00:00:00'], ['id' => 11]);

        $pdo = $this->createMock(\PDO::class);
        $pdo->expects($this->exactly(2))
            ->method('inTransaction')
            ->willReturnOnConsecutiveCalls(false, true);
        $pdo->expects($this->once())->method('beginTransaction');
        $pdo->expects($this->once())->method('rollBack');
        $pdo->expects($this->never())->method('commit');

        $job = $this->getMockBuilder(Job::class)
            ->onlyMethods(['getPdo', 'setupEnvironment', 'newJob', 'loadFromSQL', 'deleteFromSQL', 'scheduleJobRun'])
            ->getMock();

        $job->method('getPdo')->willReturn($pdo);
        $job->expects($this->once())->method('setupEnvironment');
        $job->expects($this->once())->method('newJob')->willReturn(77);
        $job->expects($this->once())
            ->method('loadFromSQL')
            ->with(77)
            ->willThrowException(new \RuntimeException('Injected failure'));
        $job->expects($this->once())->method('deleteFromSQL')->with(['id' => 77]);
        $job->expects($this->never())->method('scheduleJobRun');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Injected failure');

        $job->prepareJob($runTemplate, new ConfigFields(), new \DateTime('2026-01-01 00:00:00'), 'Native', 'cron');
    }
}

