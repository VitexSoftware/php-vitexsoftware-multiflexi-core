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

namespace MultiFlexi\Reporting;

/**
 * Description of JobReport.
 *
 * @author vitex
 */
class JobReport extends \Ease\Sand
{
    /**
     * Job Metrics data keeper.
     */
    public function __contstruct(): void
    {
        $this->setDataValue('phase', 'loaded');
        $this->setDataValue('job_id', null);
        $this->setDataValue('app_id', null);
        $this->setDataValue('app_name', null);
        $this->setDataValue('begin', null);
        $this->setDataValue('end', null);
        $this->setDataValue('scheduled', null);
        $this->setDataValue('schedule_type', null);
        $this->setDataValue('company_id', null);
        $this->setDataValue('company_name', null);
        $this->setDataValue('company_code', null);
        $this->setDataValue('runtemplate_id', null);
        $this->setDataValue('exitcode', null);
        $this->setDataValue('exitcode_description', _('n/a'));
        $this->setDataValue('stdout', null);
        $this->setDataValue('stderr', null);
        $this->setDataValue('executor', null);
        $this->setDataValue('launched_by_id', null);
        $this->setDataValue('launched_by', null);
        $this->setDataValue('data', null);
        $this->setDataValue('pid', null);
        $this->setDataValue('interval_seconds', null);
        $this->setObjectName('JobReport');
    }
}
