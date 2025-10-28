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

class CompanyJob extends DBEngine implements DatabaseEngine
{
    public $companyId;
    public $appId;
    public function __construct($init = null, $filter = [])
    {
        $this->myTable = 'job';
        parent::__construct($init, $filter);
    }
    public function setCompany($companyId): void
    {
        $this->companyId = $companyId;
        $this->filter['company_id'] = $companyId;
    }

    public function setApp($appId): void
    {
        $this->appId = $appId;
        $this->filter['app_id'] = $appId;
    }
}
