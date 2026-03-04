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

    public string $keyColumn = 'id';

    public ?string $createColumn = null;

    public ?string $lastModifiedColumn = null;

    /**
     * Internal record key value.
     */
    private int|string|null $myKey = null;

    private Company $company;

    /**
     * Internal data storage for Orm trait compatibility.
     *
     * @var array<string, mixed>|null
     */
    private ?array $data = null;

    public function __construct(Company $company, array $options = [])
    {
        parent::__construct($company->getRecordName() ?? '', $options);
        $this->company = $company;
        $this->loadEnv();
    }

    /**
     * Get the key column name.
     */
    public function getKeyColumn(): string
    {
        return $this->keyColumn;
    }

    /**
     * Get the current record key value.
     *
     * @param array<string, mixed>|null $data optional data array
     *
     * @return int|string|null
     */
    public function getMyKey(?array $data = [])
    {
        if ($data === null || $data === []) {
            return $this->myKey;
        }

        return $data[$this->keyColumn] ?? null;
    }

    /**
     * Set the current record key value.
     *
     * @param int|string $myKeyValue
     */
    public function setMyKey($myKeyValue): bool
    {
        $this->myKey = $myKeyValue;

        return true;
    }

    /**
     * Get internal data array (Orm trait compatibility).
     *
     * @return array<string, mixed>|null
     */
    public function getData(): ?array
    {
        return $this->data;
    }

    /**
     * Set a data value (Orm trait compatibility).
     */
    public function setDataValue(string $columnName, $value): bool
    {
        if ($this->data === null) {
            $this->data = [];
        }

        $this->data[$columnName] = $value;

        return true;
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
            if (null !== $this->insertToSQL(['company_id' => $this->company->getMyKey(), 'keyword' => $key, 'value' => $value])) {
                $this->setDataValue($key, $value);
            }
        } catch (\PDOException $exc) {
            // echo $exc->getTraceAsString();
        }
    }

    public function loadEnv(): void
    {
        $this->setName(\Ease\Euri::fromObject($this->company));

        foreach ($this->listingQuery()->where('company_id', $this->company->getMyKey())->fetchAll() as $companyEnvRow) {
            $this->addField(new ConfigField($companyEnvRow['keyword'], 'string', $companyEnvRow['keyword'], _('Company custom environment'), '', $companyEnvRow['value']));
        }
    }
}
