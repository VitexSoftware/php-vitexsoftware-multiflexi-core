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

        $data['required'] = \array_key_exists('required', $data) && $data['required'] === 'on' ? 1 : 0;
        $data['secret'] = \array_key_exists('secret', $data) && $data['secret'] === 'on' ? 1 : 0;
        $data['multiline'] = \array_key_exists('multiline', $data) && $data['multiline'] === 'on' ? 1 : 0;
        $data['expiring'] = \array_key_exists('expiring', $data) && $data['expiring'] === 'on' ? 1 : 0;

        return $checked ? parent::takeData($data) : 0;
    }

    /**
     * @deprecated since version 1.27 Use the addConfigFields() instead
     *
     * @param int $appId
     */
    public function appConfigs($appId): array
    {
        return $this->getColumnsFromSQL(['*'], ['app_id' => $appId], 'keyname', 'keyname');
    }

    public function addConfigFields(\MultiFlexi\Application $app): ConfigFields
    {
        $confields = new ConfigFields($app->getDataValue('name'));

        foreach ($this->appConfigs($app->getMyKey()) as $configFieldData) {
            $field = new \MultiFlexi\ConfigField($code, $type, $name, $description, $hint);
            $confields->addField($field);
        }

        return $confields;
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
