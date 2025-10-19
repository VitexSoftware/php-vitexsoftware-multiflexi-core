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
 * Description of CredentialType.
 *
 * @author Vitex <info@vitexsoftware.cz>
 */
class CredentialType extends DBEngine
{
    public static string $credTypeSchema = __DIR__.'/../../multiflexi.credential-type.schema.json';

    /**
     * @var string Name Column
     */
    public string $nameColumn = 'name';
    private ?\MultiFlexi\credentialTypeInterface $helper = null;

    public function __construct($init = null, array $filter = [])
    {
        $this->myTable = 'credential_type';
        $this->setDataValue('uuid', \Ease\Functions::guidv4());
        parent::__construct($init, $filter);
    }

    /**
     * Prepare data for save.
     */
    #[\Override]
    public function takeData(array $data): int
    {
        if (\array_key_exists('id', $data) && is_numeric($data['id'])) {
            unset($data['uuid']);
        } else {
            if (\array_key_exists('uuid', $data) === false) {
                $data['uuid'] = \Ease\Functions::guidv4();
            }
        }

        if (\array_key_exists('id', $data) && !is_numeric($data['id'])) {
            unset($data['id']);
        }

        if (\array_key_exists('name', $data) && empty($data['name'])) {
            $nameparts = [];

            if (\array_key_exists('class', $data) && $data['class']) {
                $credTypeClass = '\\MultiFlexi\\CredentialType\\'.$data['class'];
                $nameparts['class'] = $credTypeClass::name();
                $this->getHelper();
            }

            if (\array_key_exists('company_id', $data) && (int) $data['company_id']) {
                $nameparts['company'] = (new Company((int) $data['company_id']))->getRecordName();
            }

            $data['name'] = implode(' / ', $nameparts);
        }

        return parent::takeData($data);
    }

    public function loadFromSQL($id = null)
    {
        $loaded = parent::loadFromSQL($id);
        $class = $this->getDataValue('class');

        if ($class) {
            $this->getHelper();

            if (empty($this->getDataValue('logo'))) {
                $this->setDataValue('logo', $this->helper->logo());
            }
        }

        return $loaded;
    }

    public function getHelper(): ?\MultiFlexi\credentialTypeInterface
    {
        $class = $this->getDataValue('class');

        if ($class) {
            $credTypeClass = '\\MultiFlexi\\CredentialType\\'.$class;

            if ((\is_object($this->helper) === false) || (\Ease\Functions::baseClassName($this->helper) !== $class)) {
                $this->helper = new $credTypeClass();

                if ($this->getMyKey() && method_exists($this->helper, 'load')) {
                    $this->helper->load($this->getMyKey());
                }
            }
        }

        return $this->helper;
    }

    public function getFields(): ConfigFields
    {
        $fields = new ConfigFields();
        $fielder = new \MultiFlexi\CrTypeField();

        if ($this->getHelper()) {
            foreach ($this->getHelper()->fieldsProvided() as $providedField) {
                if ($providedField->isRequired()) {
                    $rField = new ConfigFieldWithHelper($providedField->getCode(), $providedField->getType(), $providedField->getName(), $providedField->getDescription());
                    $rField->setHint($providedField->getHint())->setDefaultValue($providedField->getDefaultValue())->setRequired(true)->setManual($providedField->isManual())->setMultiLine($providedField->isMultiline())->setHelper(\Ease\Functions::baseClassName($this->getHelper()));
                    $fields->addField($rField);
                }
            }
        }

        foreach ($fielder->listingQuery()->where(['credential_type_id' => $this->getMyKey()]) as $fieldData) {
            $field = new ConfigFieldWithHelper((string) $fieldData['keyname'], $fieldData['type'], $fieldData['keyname'], (string) $fieldData['description']);
            $field->setHint($fieldData['hint'])->setDefaultValue($fieldData['defval'])->setRequired($fieldData['required'] === 1)->setHelper((string) $fieldData['helper']);
            $field->setMyKey($fieldData['id']);

            if (empty($fieldData['helper']) === false) {
                $fieldHelper = $this->getHelper()->fieldsProvided()->getFieldByCode($fieldData['helper']);

                if ($fieldHelper) {
                    $field->setManual($fieldHelper->isManual());
                    $field->setRequired($fieldHelper->isRequired());
                    $field->setSecret($fieldHelper->isSecret());
                } else {
                    $this->addStatusMessage(sprintf(_('Unexistent field helper %s ?!?'), $fieldData['helper']), 'info'); // TODO:
                }
            }

            $fields->addField($field);
        }

        return $fields;
    }

    public function getCredTypeFields(self $credentialType): ConfigFields
    {
    }

    public function query(): ConfigFields
    {
        $fields = $this->getFields();

        if ($this->getHelper()) {
            $fields->addFields($this->getHelper()->query());
        }

        return $fields;
    }

    public function getLogo(): string
    {
        return (string) $this->getDataValue('logo');
    }

    /**
     * Import credential type from JSON file with validation.
     *
     * @param string $jsonFile Path to JSON file
     *
     * @throws \Exception on validation errors or duplicate entries
     *
     * @return bool Success status
     */
    public function importCredTypeJson(string $jsonFile): bool
    {
        if (!file_exists($jsonFile)) {
            throw new \Exception("File not found: {$jsonFile}");
        }

        $jsonContent = file_get_contents($jsonFile);

        if ($jsonContent === false) {
            throw new \Exception("Cannot read file: {$jsonFile}");
        }

        $data = json_decode($jsonContent, true);

        if (json_last_error() !== \JSON_ERROR_NONE) {
            throw new \Exception('Invalid JSON data: '.json_last_error_msg());
        }

        // Validate required fields
        $requiredFields = ['id', 'uuid', 'name', 'description', 'fields'];

        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                throw new \Exception("Missing required field: {$field}");
            }
        }

        // Check if credential type with this UUID already exists
        $existing = $this->listingQuery()->where(['uuid' => $data['uuid']])->fetch();

        if ($existing) {
            throw new \Exception("Credential type with UUID {$data['uuid']} already exists");
        }

        // Check if credential type with this ID already exists
        $existing = $this->listingQuery()->where(['id' => $data['id']])->fetch();

        if ($existing) {
            throw new \Exception("Credential type with ID {$data['id']} already exists");
        }

        // Prepare data for database insertion
        $insertData = [
            'id' => $data['id'],
            'uuid' => $data['uuid'],
            'name' => \is_array($data['name']) ? json_encode($data['name']) : $data['name'],
            'description' => \is_array($data['description']) ? json_encode($data['description']) : $data['description'],
            'fields' => json_encode($data['fields']),
        ];

        // Add optional fields if present
        if (isset($data['code'])) {
            $insertData['code'] = $data['code'];
        }

        // Clear current data and set new data
        $this->unsetDataValue($this->getKeyColumn());
        $this->setData($insertData);

        // Insert into database
        $result = $this->insertToSQL($insertData);

        return $result !== false;
    }
}
