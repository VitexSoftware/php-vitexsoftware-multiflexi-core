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

        // Update next_schedule first to prevent race condition
        $job->getRuntemplate()->updateToSQL(['next_schedule' => $when->format('Y-m-d H:i:s')], ['id' => $job->getRuntemplate()->getMyKey()]);

        try {
            return $this->insertToSQL([
                'after' => $when->format('Y-m-d H:i:s'),
                'job' => $jobId,
            ]);
        } catch (\PDOException $e) {
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
     * Get Job Interval by Code.
     */
    public static function codeToInterval(string $code): string
    {
        return \array_key_exists($code, self::$intervalCode) ? self::$intervalCode[$code] : 'n/a';
    }

    /**
     * Get Job Interval by Code.
     *
     * @return int Interval length in seconds
     */
    public static function codeToSeconds(string $code): int
    {
        return \array_key_exists($code, self::$intervalSecond) ? (int) (self::$intervalSecond[$code]) : 0;
    }

    /**
     * Get Interval code by Name.
     */
    public static function intervalToCode(string $interval): string
    {
        return \array_key_exists($interval, array_flip(self::$intervalCode)) ? array_flip(self::$intervalCode)[$interval] : 'n/a';
    }
}
