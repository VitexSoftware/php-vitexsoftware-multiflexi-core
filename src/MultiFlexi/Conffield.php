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
 * Description of Conf field.
 *
 * @author vitex
 */
class Conffield extends Engine
{
    public function __construct($identifier = null, $options = [])
    {
        $this->myTable = 'conffield';
        parent::__construct($identifier, $options);
    }

    #[\Override]
    public function takeData(array $data): int
    {
        $checked = false;
        unset($data['add']);

        if (\array_key_exists('app_id', $data)) {
            $checked = true;
        }

        if (\array_key_exists('id', $data) && ($data['id'] === '')) {
            unset($data['id']);
            $checked = true;
        }

        // This method doubles as the SQL-row hydrator (called from
        // loadFromSQL()) and the HTML-form-submission handler (called with
        // $_POST). A raw DB row always carries these columns as an
        // already-boolean 0/1 (int or numeric string); an HTML checkbox
        // carries them as the literal string 'on' when checked, and is
        // simply absent from the array when unchecked. Both representations
        // must be normalized to 0/1 without clobbering a DB-loaded 1 back to
        // 0 just because it isn't literally 'on'.
        foreach (['required', 'secret', 'multiline', 'expiring'] as $boolField) {
            $rawValue = $data[$boolField] ?? null;
            $data[$boolField] = \in_array($rawValue, ['on', 1, '1', true], true) ? 1 : 0;
        }

        return $checked ? parent::takeData($data) : 0;
    }

    /**
     * @param int $appId
     */
    public function appConfigs($appId): array
    {
        return $this->getColumnsFromSQL(['*'], ['app_id' => $appId], 'keyname', 'keyname');
    }

    /**
     * Create new Environment field for an application.
     *
     * @param int    $appId
     * @param string $envName
     * @param array  $envProperties
     */
    public function addAppConfig($appId, $envName, $envProperties)
    {
        $this->dataReset();

        $candidat = $this->listingQuery()->where('app_id', $appId)->where('keyname', $envName);

        if (!empty($candidat)) {
            $currentData = $candidat->fetch();

            if ($currentData) {
                $this->setMyKey($currentData['id']);
            }
        }

        $this->setDataValue('app_id', $appId);
        $this->setDataValue('keyname', $envName);

        $this->setDataValue('type', $envProperties['type']);
        $this->setDataValue('description', $envProperties['description']);
        $this->setDataValue('defval', \array_key_exists('defval', $envProperties) ? $envProperties['defval'] : '');
        $this->setDataValue('name', \array_key_exists('name', $envProperties) ? $envProperties['name'] : '');
        $this->setDataValue('hint', \array_key_exists('hint', $envProperties) ? $envProperties['hint'] : '');
        $this->setDataValue('note', \array_key_exists('note', $envProperties) ? $envProperties['note'] : '');
        $this->setDataValue('required', !empty($envProperties['required']) ? 1 : 0);
        $this->setDataValue('secret', !empty($envProperties['secret']) ? 1 : 0);
        $this->setDataValue('multiline', !empty($envProperties['multiline']) ? 1 : 0);
        $this->setDataValue('expiring', !empty($envProperties['expiring']) ? 1 : 0);

        return $this->dbsync();
    }

    public static function getAppConfigs(Application $app): ConfigFields
    {
        $appConfiguration = new ConfigFields(\Ease\Euri::fromObject($app));

        foreach ((new self())->appConfigs($app->getMyKey()) as $appConfig) {
            $displayName = !empty($appConfig['name']) ? $appConfig['name'] : $appConfig['keyname'];
            $hint = $appConfig['hint'] ?? '';
            $field = new ConfigField($appConfig['keyname'], self::fixType($appConfig['type']), $displayName, $appConfig['description'], $hint);
            $field->setRequired($appConfig['required'] === 1)
                ->setDefaultValue($appConfig['defval'])
                ->setSource(\Ease\Euri::fromObject($app))
                ->setNote($appConfig['note'] ?? '')
                ->setCategory($appConfig['category'] ?? '')
                ->setSecret(!empty($appConfig['secret']))
                ->setMultiLine(!empty($appConfig['multiline']))
                ->setExpiring(!empty($appConfig['expiring']));
            $appConfiguration->addField($field);
        }

        return $appConfiguration;
    }

    /**
     * Fix Old types to new.
     */
    public static function fixType(string $typeOld): string
    {
        return str_replace(
            ['directory', 'checkbox', 'boolean', 'switch', 'text', 'number', 'select'],
            ['file-path', 'bool', 'bool', 'bool', 'string', 'integer', 'set'],
            $typeOld,
        );
    }
}
