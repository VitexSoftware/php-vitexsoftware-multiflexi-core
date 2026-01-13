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
        unset($data['csrf_token']);

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
                $rField = new ConfigFieldWithHelper($providedField->getCode(), $providedField->getType(), $providedField->getName(), $providedField->getDescription());
                $rField->setHint($providedField->getHint())->setDefaultValue($providedField->getDefaultValue())->setRequired($providedField->isRequired())->setManual($providedField->isManual())->setMultiLine($providedField->isMultiline())->setHelper(\Ease\Functions::baseClassName($this->getHelper()));
                $fields->addField($rField);
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

        // Validate required fields - code is now required instead of id
        $requiredFields = ['code', 'uuid', 'name', 'description', 'fields'];

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

        // Check if credential type with this code already exists (if code column exists)
        // For now, we'll check by name since code column may not exist yet
        $nameToCheck = \is_array($data['name']) ? $data['name']['en'] ?? json_encode($data['name']) : $data['name'];
        $existing = $this->listingQuery()->where(['name' => $nameToCheck])->fetch();

        if ($existing) {
            throw new \Exception("Credential type with name '{$nameToCheck}' already exists");
        }

        // Prepare data for database insertion - adapt to current schema
        // Current schema has: id, name, url, logo, uuid, class, company_id
        $insertData = [
            'uuid' => $data['uuid'],
        ];

        // Handle name - combine code with localized name
        if (\is_array($data['name'])) {
            $insertData['name'] = $data['code'].' - '.($data['name']['en'] ?? reset($data['name']));
        } else {
            $insertData['name'] = $data['code'].' - '.$data['name'];
        }

        // Note: description field doesn't exist in current schema
        // It will need to be stored in translations table

        // Add optional fields if present in current schema
        if (isset($data['class'])) {
            $insertData['class'] = $data['class'];
        }

        if (isset($data['version'])) {
            $insertData['version'] = $data['version'];
        }

        if (isset($data['logo'])) {
            $insertData['logo'] = $data['logo'];
        }

        if (isset($data['url'])) {
            $insertData['url'] = $data['url'];
        }

        // Set default company_id to 1 (or handle this properly)
        $insertData['company_id'] = 1; // TODO: This should be configurable

        // Clear current data and set new data
        $this->unsetDataValue($this->getKeyColumn());
        $this->setData($insertData);

        // Insert into database
        $result = $this->insertToSQL($insertData);

        if ($result !== false) {
            $credentialTypeId = $this->getMyKey();

            // Handle localized translations if they exist
            $translationEngine = new \Ease\SQL\Engine();
            $translationEngine->myTable = 'credential_type_translations';

            // Store name translations
            if (\is_array($data['name']) && $credentialTypeId) {
                foreach ($data['name'] as $lang => $value) {
                    if (!empty($value)) {
                        $translationData = [
                            'credential_type_id' => $credentialTypeId,
                            'lang' => $lang,
                            'name' => $value,
                        ];
                        $translationEngine->insertToSQL($translationData);
                    }
                }
            }

            // Store description translations (since description doesn't exist in main table)
            if (\is_array($data['description']) && $credentialTypeId) {
                foreach ($data['description'] as $lang => $value) {
                    if (!empty($value)) {
                        // Check if we already have a record for this lang, update it
                        $existing = $translationEngine->listingQuery()
                            ->where(['credential_type_id' => $credentialTypeId, 'lang' => $lang])
                            ->fetch();

                        if ($existing) {
                            $translationEngine->updateToSQL(
                                ['description' => $value],
                                ['credential_type_id' => $credentialTypeId, 'lang' => $lang],
                            );
                        } else {
                            $translationData = [
                                'credential_type_id' => $credentialTypeId,
                                'lang' => $lang,
                                'description' => $value,
                            ];
                            $translationEngine->insertToSQL($translationData);
                        }
                    }
                }
            }

            // Handle fields - insert into crtypefield table
            if (isset($data['fields']) && \is_array($data['fields'])) {
                $crTypeField = new \MultiFlexi\CrTypeField();

                foreach ($data['fields'] as $field) {
                    $fieldData = [
                        'credential_type_id' => $credentialTypeId,
                        'keyname' => $field['keyword'],
                        'type' => $field['type'],
                        'description' => \is_array($field['description'] ?? []) ? json_encode($field['description']) : ($field['description'] ?? ''),
                        'hint' => $field['hint'] ?? null,
                        'defval' => $field['default'] ?? null,
                        'required' => $field['required'] ?? false,
                    ];

                    $crTypeField->takeData($fieldData);
                    $fieldId = $crTypeField->saveToSQL();

                    // Handle field name and description translations
                    $fieldTranslationEngine = new \Ease\SQL\Engine();
                    $fieldTranslationEngine->myTable = 'credential_type_field_translations';

                    if ($fieldId && isset($field['name']) && \is_array($field['name'])) {
                        foreach ($field['name'] as $lang => $value) {
                            if (!empty($value)) {
                                $fieldTranslationData = [
                                    'crtypefield_id' => $fieldId,
                                    'lang' => $lang,
                                    'name' => $value,
                                ];
                                $fieldTranslationEngine->insertToSQL($fieldTranslationData);
                            }
                        }
                    }

                    if ($fieldId && isset($field['description']) && \is_array($field['description'])) {
                        foreach ($field['description'] as $lang => $value) {
                            if (!empty($value)) {
                                // Check if we already have a record for this field+lang, update it
                                $existing = $fieldTranslationEngine->listingQuery()
                                    ->where(['crtypefield_id' => $fieldId, 'lang' => $lang])
                                    ->fetch();

                                if ($existing) {
                                    $fieldTranslationEngine->updateToSQL(
                                        ['description' => $value],
                                        ['crtypefield_id' => $fieldId, 'lang' => $lang],
                                    );
                                } else {
                                    $fieldTranslationData = [
                                        'crtypefield_id' => $fieldId,
                                        'lang' => $lang,
                                        'description' => $value,
                                    ];
                                    $fieldTranslationEngine->insertToSQL($fieldTranslationData);
                                }
                            }
                        }
                    }

                    // Reset for next field
                    $crTypeField = new \MultiFlexi\CrTypeField();
                }
            }
        }

        return $result !== false;
    }
}
