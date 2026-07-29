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
 * Data-access base for the `schedule` (job queue) table, joined with the
 * job/runtemplate/apps/company context shared by every queue listing —
 * used directly by CLI commands (multiflexi-cli queue:list/queue:overview)
 * and as the base class for multiflexi-web5's HTML/DataTables presentation
 * layer (MultiFlexi\ScheduleLister there), which adds columns/HTML rendering.
 */
class ScheduleQueue extends DBEngine
{
    public function __construct($identifier = null, $options = [])
    {
        $this->myTable = 'schedule';
        $this->nameColumn = '';
        parent::__construct($identifier, $options);
    }

    public function listingQuery(): \Envms\FluentPDO\Queries\Select
    {
        return parent::listingQuery()
            ->leftJoin('job ON job.id = schedule.job')->select(['job.schedule_type'])
            ->leftJoin('user ON user.id = job.launched_by')
            ->leftJoin('runtemplate ON runtemplate.id = job.runtemplate_id')->select(['runtemplate.name AS runtemplate_name', 'runtemplate.id AS runtemplate_id', 'runtemplate.interv AS runtemplate_interv', 'runtemplate.cron AS runtemplate_cron'])
            ->leftJoin('apps ON apps.id = runtemplate.app_id')->select(['apps.name AS app_name', 'apps.id AS app_id'])
            ->leftJoin('company ON company.id = runtemplate.company_id')->select(['company.name AS company_name', 'company.id AS company_id']);
    }
}
