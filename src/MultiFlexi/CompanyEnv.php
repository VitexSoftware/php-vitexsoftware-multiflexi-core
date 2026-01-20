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
 * Description of CompanyEnv.
 *
 * @author vitex
 */
class CompanyEnv extends ConfigFields
{
    use \Ease\SQL\Orm;
    public string $myTable = 'companyenv';
    private Company $company;
    public function __construct(Company $company, array $options = [])
    {
        parent::__construct($company->getRecordName(), $options);
        $this->company = $company;
        $this->loadEnv();
    }

    /**
     * Add Configuration to Company's Environment store.
     *
     * @param string $key   Name of Value to keep
     * @param string $value Value of Configuration
     */
    public function addEnv($key, $value): void
    {
        try {
            if (null !== $this->insertToSQL(['company_id' => $this->companyID, 'keyword' => $key, 'value' => $value])) {
                $this->setDataValue($key, $value);
            }
        } catch (\PDOException $exc) {
            // echo $exc->getTraceAsString();
        }
    }

    public function loadEnv(): void
    {
        foreach ($this->listingQuery()->where('company_id', $this->company->getMyKey())->fetchAll() as $companyEnvRow) {
            $this->addField(new ConfigField($companyEnvRow['keyword'], 'string', $companyEnvRow['keyword'], _('Company custom environment'), '', $companyEnvRow['value']));
        }
    }
}
