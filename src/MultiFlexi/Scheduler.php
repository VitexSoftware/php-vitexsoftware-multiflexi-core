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
    // Convert 'inter' values to cron expressions
    public static $intervCron = [
        'y' => '0 0 1 1 *',    // yearly
        'm' => '0 0 1 * *',    // monthly
        'w' => '0 0 * * 0',    // weekly (Sunday)
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

    /**
     * Save Job execution time.
     */
    public function addJob(Job $job, \DateTime $when)
    {
        $job->getRuntemplate()->updateToSQL(['last_schedule' => $when->format('Y-m-d H:i:s')], ['id' => $job->getRuntemplate()->getMyKey()]);

        return $this->insertToSQL([
            'after' => $when->format('Y-m-d H:i:s'),
            'job' => $job->getMyKey(),
        ]);
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
}
