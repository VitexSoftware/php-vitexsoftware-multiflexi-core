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
 * Description of Configuration.
 *
 * @author vitex
 */
class Configuration extends \Ease\SQL\Engine
{
    public function __construct($identifier = null, $options = [])
    {
        $this->myTable = 'configuration';
        parent::__construct($identifier, $options);
    }

    /**
     * Save data array to SQL. If $searchForID is false, update if keyColumn is set.
     *
     * @param array $data        Associative array of data
     * @param bool  $searchForID Determine whether to update or insert
     *
     * @return null|int Record ID or null on failure
     */
    #[\Override]
    public function saveToSQL($data = null, $searchForID = false)
    {
        if (null === $data) {
            $data = $this->getData();
        }

        $result = 0;
        unset($data['id'], $data['class'], $data['app_id'], $data['company_id'], $data['runtemplate_id']);

        $condition = [];

        if ($this->getDataValue('app_id')) {
            $condition['app_id'] = $this->getDataValue('app_id');
        }

        if ($this->getDataValue('company_id')) {
            $condition['company_id'] = $this->getDataValue('company_id');
        }

        if ($this->getDataValue('runtemplate_id')) {
            $condition['runtemplate_id'] = $this->getDataValue('runtemplate_id');
        }

        foreach ($data as $column => $value) {
            $condition['name'] = $column;
            $this->deleteFromSQL($condition);
            $result += $this->insertToSQL(['app_id' => $this->getDataValue('app_id'), 'company_id' => $this->getDataValue('company_id'), 'runtemplate_id' => $this->getDataValue('runtemplate_id'), 'name' => $column, 'value' => $value]);
        }

        return $result;
    }

    public function getConfigFields(): ConfigFields
    {
        $cnf = new ConfigFields('');

        foreach ((array) $this->getData() as $cfgRaw) {
            $type = $cfgRaw['config_type'] ?? ($cfgRaw['type'] ?? 'string');
            $cnf->addField(new ConfigField($cfgRaw['name'], $type, $cfgRaw['name'], '', '', $cfgRaw['value']));
        }

        return $cnf;
    }

    /**
     * Převezme data do aktuálního pole dat.
     *
     * Bool fields are normalised to 'true'/'false':
     *  - form mode (default): HTML checkbox semantics – an absent field means
     *    an unchecked checkbox ('false'), a present one a checked one ('true');
     *  - partial mode: only fields present in $data are touched and their value
     *    decides ('true', '1', 'yes', 'on' => 'true', anything else => 'false').
     *    Absent bool fields are left out, so their stored value is kept.
     *    Use it for CLI/API updates that send only the changed keys.
     *
     * @param array $data    asociativní pole dat
     * @param bool  $partial partial update instead of a complete form submit
     */
    public function takeData(array $data, bool $partial = false): int
    {
        $cfgs = new Conffield();

        foreach ($cfgs->appConfigs($this->getDataValue('app_id')) as $cfg) {
            if (Conffield::fixType($cfg['type']) === 'bool') {
                $key = $cfg['keyname'];

                if ($partial) {
                    if (\array_key_exists($key, $data)) {
                        $data[$key] = self::boolString($data[$key]);
                    }
                } else {
                    $data[$key] = \array_key_exists($key, $data) ? 'true' : 'false';
                }
            }
        }

        return parent::takeData($data);
    }

    /**
     * Normalise a bool-ish config value to 'true' or 'false'.
     */
    public static function boolString(mixed $value): string
    {
        if ($value instanceof ConfigField) {
            $value = $value->getValue();
        }

        if (\is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return \in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true) ? 'true' : 'false';
    }

    /**
     * Apply Configuration.
     *
     * @param int $companyId
     * @param int $appId
     */
    public function setEnvironment($companyId, $appId): void
    {
        foreach ($this->getAppConfig($companyId, $appId) as $cfgRaw) {
            $this->addStatusMessage(sprintf(_('Setting Environment %s to %s'), $cfgRaw['name'], $cfgRaw['value']), 'debug');
            putenv($cfgRaw['name'].'='.$cfgRaw['value']);
        }
    }

    /**
     * App Configuration values.
     */
    public function getAppConfig(int $companyId, int $appId): array
    {
        $appConfig = [];
        $appConfigRaw = $this->getColumnsFromSQL(['name', 'value'], ['company_id' => $companyId, 'app_id' => $appId]);

        foreach ($appConfigRaw as $cfg) {
            $appConfig[$cfg['name']] = $cfg['value'];
        }

        return $appConfig;
    }
}
