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

use Cron\CronExpression;

/**
 * Description of Scheduler.
 *
 * @author vitex
 */
class Scheduler extends Engine
{
    public static array $intervalCode = [
        'c' => 'custom',
        'y' => 'yearly',
        'm' => 'monthly',
        'w' => 'weekly',
        'd' => 'daily',
        'h' => 'hourly',
        'i' => 'minutly',
        'n' => 'disabled',
    ];
    public static array $intervalSecond = [
        'c' => '',
        'n' => '0',
        'i' => '60',
        'h' => '3600',
        'd' => '86400',
        'w' => '604800',
        'm' => '2629743',
        'y' => '31556926',
    ];

    /**
     * Convert 'inter' values to cron expressions.
     *
     * @var array<string, string>
     */
    public static array $intervCron = [
        'y' => '0 0 1 1 *',    // yearly
        'm' => '0 0 1 * *',    // monthly
        'w' => '0 0 * * 1',    // weekly (Monday)
        'd' => '0 0 * * *',    // daily
        'h' => '0 * * * *',    // hourly
        'i' => '* * * * *',    // minutely
        'n' => '',             // disabled
    ];

    public function __construct($identifier = null, $options = [])
    {
        $this->myTable = 'schedule';
        $this->nameColumn = '';
        parent::__construct($identifier, $options);
    }

    public static function getIntervalEmoji(string $interval): string
    {
        $emojis = [
            'c' => '🔵',
            'n' => '🔴',
            'i' => '⏳',
            'h' => '🕰️',
            'd' => '☀️',
            'w' => '📅',
            'm' => '🌛',
            'y' => '🎆',
            '' => '',
        ];

        return \array_key_exists($interval, $emojis) ? $emojis[$interval] : '';
    }

    /**
     * Validate that runtemplates with next_schedule have corresponding jobs in queue
     * Reset next_schedule to null if job is missing.
     */
    public function initializeScheduling(): void
    {
        $jobber = new Job();
        $runtemplateQuery = new \MultiFlexi\RunTemplate();
        // Get all active runtemplates that have next_schedule set
        $runtemplates = $runtemplateQuery->getColumnsFromSQL(['id', 'next_schedule', 'company_id'], ['active' => true, 'interv != ?' => 'n']);

        foreach ($runtemplates as $rtData) {
            // Check if there's a scheduled job at the expected time
            $expectedJob = $jobber->listingQuery()->where(['runtemplate_id' => $rtData['id'], 'schedule' => $rtData['next_schedule'], 'exitcode' => null])->fetch();

            if (!$expectedJob) {
                // No job found for this next_schedule time, reset it to null
                $runtemplateQuery->updateToSQL(['next_schedule' => null], ['id' => $rtData['id']]);
                $this->addStatusMessage('Reset next_schedule for runtemplate #'.$rtData['id'].' - missing job in queue', 'debug');
            }
        }
    }

    /**
     * Clean up orphaned jobs from queue that don't have valid company or runtemplate references.
     */
    public function cleanupOrphanedJobs(): void
    {
        $jobber = new Job();

        // Remove jobs without company_id or runtemplate_id
        $orphanedJobs = $jobber->listingQuery()
            ->where('company_id IS NULL OR runtemplate_id IS NULL OR company_id = 0 OR runtemplate_id = 0')
            ->fetchAll();

        foreach ($orphanedJobs as $job) {
            $jobber->deleteFromSQL(['id' => $job['id']]);
            $this->addStatusMessage('Removed orphaned job #'.$job['id'].' - missing company_id or runtemplate_id', 'info');
        }

        // Remove jobs with invalid company references
        $invalidCompanyJobs = $jobber->listingQuery()
            ->leftJoin('company ON company.id = job.company_id')
            ->where('job.company_id IS NOT NULL AND company.id IS NULL')
            ->select(['job.id'])
            ->fetchAll();

        foreach ($invalidCompanyJobs as $job) {
            $jobber->deleteFromSQL(['id' => $job['id']]);
            $this->addStatusMessage('Removed job #'.$job['id'].' - invalid company reference', 'info');
        }

        // Remove jobs with invalid runtemplate references
        $invalidRuntemplateJobs = $jobber->listingQuery()
            ->leftJoin('runtemplate ON runtemplate.id = job.runtemplate_id')
            ->where('job.runtemplate_id IS NOT NULL AND runtemplate.id IS NULL')
            ->select(['job.id'])
            ->fetchAll();

        foreach ($invalidRuntemplateJobs as $job) {
            $jobber->deleteFromSQL(['id' => $job['id']]);
            $this->addStatusMessage('Removed job #'.$job['id'].' - invalid runtemplate reference', 'info');
        }
    }

    /**
     * Remove broken records from the queue table before adding new jobs.
     */
    public function purgeBrokenQueueRecords(): void
    {
        // Purge records where 'job' is NULL, 0, or empty
        $brokenRecords = $this->listingQuery()
            ->where('job IS NULL OR job = 0 OR job = ""')
            ->fetchAll();

        foreach ($brokenRecords as $record) {
            $this->deleteFromSQL(['id' => $record['id']]);
            $this->addStatusMessage('Purged broken queue record id='.$record['id'], 'info');
        }

        // Purge records where referenced job does not exist
        $allSchedules = $this->listingQuery()->fetchAll();
        $jobber = new Job();

        foreach ($allSchedules as $schedule) {
            if (empty($schedule['job'])) {
                continue; // already handled above
            }

            $jobExists = $jobber->listingQuery()->where('id', $schedule['job'])->fetch();

            if (!$jobExists) {
                $this->deleteFromSQL(['id' => $schedule['id']]);
                $this->addStatusMessage('Purged schedule id='.$schedule['id'].' referencing missing job id='.$schedule['job'], 'info');
            }
        }
    }

    /**
     * Detect and purge pending jobs whose scheduled 'after' time lies
     * implausibly far in the future for their runtemplate's interval
     * (e.g. a daily runtemplate with a job scheduled 4 days out). This can
     * happen when a runtemplate's next_schedule/last_schedule state gets
     * lost (e.g. queue:truncate) and the scheduler computes a wrong
     * anchor. Purging the record and resetting next_schedule lets the
     * running scheduler daemon recompute a correct schedule on its next
     * tick.
     */
    public function purgeImplausibleSchedules(): void
    {
        $jobber = new Job();
        $runtemplateQuery = new \MultiFlexi\RunTemplate();

        $pendingJobs = $jobber->listingQuery()
            ->where('job.exitcode IS NULL AND job.begin IS NULL')
            ->where('job.runtemplate_id IS NOT NULL')
            ->select(['job.id', 'job.runtemplate_id', 'job.schedule'])
            ->fetchAll();

        foreach ($pendingJobs as $pendingJob) {
            $scheduleEntry = $this->listingQuery()->where('job', $pendingJob['id'])->fetch();

            if (!$scheduleEntry || empty($scheduleEntry['after'])) {
                continue;
            }

            $runtemplateData = $runtemplateQuery->getColumnsFromSQL(
                ['id', 'interv', 'cron', 'delay'],
                ['id' => $pendingJob['runtemplate_id']],
            );
            $runtemplateData = $runtemplateData[0] ?? null;

            if (!$runtemplateData || $runtemplateData['interv'] === 'n' || empty($runtemplateData['interv'])) {
                continue;
            }

            $after = new \DateTime($scheduleEntry['after']);
            $now = new \DateTime();

            if ($runtemplateData['interv'] === 'c') {
                if (empty($runtemplateData['cron'])) {
                    continue;
                }

                $sanityBoundary = (new CronExpression($runtemplateData['cron']))->getNextRunDate($now, 1);
            } else {
                $periodSeconds = self::codeToSeconds($runtemplateData['interv']);

                if ($periodSeconds <= 0) {
                    continue;
                }

                $sanityBoundary = (clone $now)->modify('+'.$periodSeconds.' seconds');
            }

            if (!empty($runtemplateData['delay'])) {
                $sanityBoundary->modify('+'.$runtemplateData['delay'].' seconds');
            }

            if ($after > $sanityBoundary) {
                $this->deleteFromSQL(['id' => $scheduleEntry['id']]);
                $jobber->deleteFromSQL(['id' => $pendingJob['id']]);
                $runtemplateQuery->updateToSQL(['next_schedule' => null], ['id' => $runtemplateData['id']]);

                $this->addStatusMessage(
                    sprintf(
                        'Purged implausible schedule for runtemplate #%d: job #%d scheduled at %s, sanity boundary %s',
                        $runtemplateData['id'],
                        $pendingJob['id'],
                        $after->format('Y-m-d H:i:s'),
                        $sanityBoundary->format('Y-m-d H:i:s'),
                    ),
                    'warning',
                );
            }
        }
    }

    /**
     * Save Job execution time.
     */
    public function addJob(Job $job, \DateTime $when)
    {
        $jobId = $job->getMyKey();

        // Check if this job is already scheduled to prevent duplicates
        $existingSchedule = $this->listingQuery()
            ->where('job', $jobId)
            ->fetch();

        if ($existingSchedule) {
            $this->addStatusMessage(
                sprintf(_('Job #%d is already scheduled, skipping duplicate'), $jobId),
                'debug',
            );

            return (int) $existingSchedule['id'];
        }

        // Skip for ad-hoc/CommandLine jobs — they must not overwrite the cron scheduler's next_schedule
        $scheduleType = $job->getDataValue('schedule_type');
        $runtemplate = Job::isAdhocScheduleType($scheduleType) ? null : $job->getRuntemplate();

        // next_schedule and the schedule/queue row must become visible together:
        // a reader (e.g. purgeBrokenQueueRecords()/initializeScheduling() running
        // concurrently in the daemon loop) must never observe next_schedule already
        // set while the corresponding schedule row is still missing, or vice versa.
        $pdo = $this->getPdo();
        $ownsTransaction = !$pdo->inTransaction();

        if ($ownsTransaction) {
            $pdo->beginTransaction();
        }

        try {
            if ($runtemplate !== null) {
                $runtemplate->updateToSQL(['next_schedule' => $when->format('Y-m-d H:i:s')], ['id' => $runtemplate->getMyKey()]);
            }

            $scheduleId = $this->insertToSQL([
                'after' => $when->format('Y-m-d H:i:s'),
                'job' => $jobId,
            ]);

            if ($ownsTransaction) {
                $pdo->commit();
            }

            return $scheduleId;
        } catch (\PDOException $e) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            // Handle duplicate key error gracefully (SQLSTATE 23000)
            if (str_contains($e->getMessage(), 'Duplicate entry') || $e->getCode() === '23000') {
                $this->addStatusMessage(
                    sprintf(_('Job #%d already scheduled (caught duplicate key error)'), $jobId),
                    'debug',
                );

                // Return existing schedule ID
                $existing = $this->listingQuery()->where('job', $jobId)->fetch();

                return $existing ? (int) $existing['id'] : 0;
            }

            // Re-throw if it's a different error
            throw $e;
        } catch (\Throwable $t) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $t;
        }
    }

    /**
     * @return int
     */
    public function getCurrentJobs()
    {
        $databaseType = $this->getPdo()->getAttribute(\PDO::ATTR_DRIVER_NAME);

        switch ($databaseType) {
            case 'mysql':
                $condition = 'UNIX_TIMESTAMP(after) < UNIX_TIMESTAMP(NOW())';

                break;
            case 'sqlite':
                $condition = "strftime('%s', after) < strftime('%s', 'now')";

                break;
            case 'pgsql':
                $condition = 'EXTRACT(EPOCH FROM after) < EXTRACT(EPOCH FROM NOW())';

                break;
            case 'sqlsrv':
                $condition = "DATEDIFF(second, '1970-01-01', after) < DATEDIFF(second, '1970-01-01', GETDATE())";

                break;

            default:
                throw new \Exception('Unsupported database type '.$databaseType);
        }

        return $this->listingQuery()->orderBy('after')->where($condition);
    }

    /**
     * Get Job Interval human-readable name by code.
     *
     * Known codes: y, m, w, d, h, i, n (disabled), c (custom cron).
     * Returns 'n/a' for unknown or null codes (e.g. RunTemplate with no schedule set).
     */
    public static function codeToInterval(?string $code): string
    {
        return ($code !== null && \array_key_exists($code, self::$intervalCode)) ? self::$intervalCode[$code] : 'n/a';
    }

    /**
     * Get Job Interval length in seconds by code.
     *
     * Known codes: y, m, w, d, h, i, n (disabled), c (custom cron).
     * For code 'c', pass the RunTemplate's cron expression via $cron to get
     * an estimated interval (average gap between upcoming occurrences).
     * Returns 0 for unknown or null codes, or for 'c' without a usable
     * $cron expression (e.g. RunTemplate with no schedule set).
     */
    public static function codeToSeconds(?string $code, ?string $cron = null): int
    {
        if ($code === 'c') {
            return self::cronToAverageSeconds($cron);
        }

        return ($code !== null && \array_key_exists($code, self::$intervalSecond)) ? (int) (self::$intervalSecond[$code]) : 0;
    }

    /**
     * Estimate the average interval in seconds between consecutive runs of
     * a cron expression by sampling a few of its upcoming occurrences.
     *
     * A plain "next run minus now" would be skewed by however close 'now'
     * happens to be to the next occurrence, so this instead averages the
     * gaps across several consecutive occurrences (e.g. an expression
     * firing at 6,8,10,...,22h averages to 2h despite the 8h overnight
     * gap).
     */
    private static function cronToAverageSeconds(?string $cron, int $samples = 6): int
    {
        if (empty($cron)) {
            return 0;
        }

        try {
            $runDates = (new CronExpression($cron))->getMultipleRunDates($samples, new \DateTime(), false, true);
        } catch (\Exception $exception) {
            return 0;
        }

        if (\count($runDates) < 2) {
            return 0;
        }

        $span = end($runDates)->getTimestamp() - reset($runDates)->getTimestamp();

        return (int) round($span / (\count($runDates) - 1));
    }

    /**
     * Get Interval code by Name.
     */
    public static function intervalToCode(string $interval): string
    {
        return \array_key_exists($interval, array_flip(self::$intervalCode)) ? array_flip(self::$intervalCode)[$interval] : 'n/a';
    }
}
