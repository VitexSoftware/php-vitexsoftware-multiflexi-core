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
 * Description of CredentialProtoType.
 *
 * @author Vitex <info@vitexsoftware.cz>
 */
class CredentialProtoType extends \MultiFlexi\Engine
{
    use \Ease\recordkey;

    /**
     * Database table name.
     */
    public string $myTable = 'credential_prototype';

    /**
     * Name Column.
     */
    public string $nameColumn = 'name';

    /**
     * Schema path for credential prototype JSON validation.
     */
    public static string $credProtoTypeSchema = __DIR__.'/../../schema/credential-prototype.json';
    protected \MultiFlexi\ConfigFields $configFieldsProvided;
    protected \MultiFlexi\ConfigFields $configFieldsInternal;

    /**
     * Credential protype helper class.
     *
     * @param int|string $init
     */
    public function __construct($init = null, array $options = [])
    {
        $this->createColumn = 'created_at';
        $this->lastModifiedColumn = 'updated_at';
        $this->configFieldsProvided = new \MultiFlexi\ConfigFields(\Ease\Functions::baseClassName($this).' provided');
        $this->configFieldsInternal = new \MultiFlexi\ConfigFields(\Ease\Functions::baseClassName($this).' Internal');
        parent::__construct($init, $options);
    }

    public function load(int $credTypeId)
    {
        $loader = new \MultiFlexi\CrTypeOption();

        return $this->takeData($loader->listingQuery()->where('credential_type_id', $credTypeId)->fetchAll('name'));
    }

    public function save(): bool
    {
        $credentialTypeId = $this->getDataValue('credential_type_id');

        $fielder = new \MultiFlexi\CrTypeOption();

        foreach ($this->fieldsInternal() as $keyName => $field) {
            $fielder->dataReset();
            $subject = ['name' => $keyName, 'credential_type_id' => $credentialTypeId];
            $fielder->loadFromSQL($subject);
            $rowId = $fielder->getMyKey();
            $fielder->dataReset();

            if ($rowId) {
                $fielder->setMyKey($rowId);
            }

            $fielder->takeData(array_merge($subject, ['type' => $field->getType(), 'value' => $field->getValue()]));
            $fielder->saveToSQL();
        }

        return true;
    }

    /**
     * Set defVal where value is not set.
     */
    public function prepareConfigForm(): void
    {
        foreach ($this->configFieldsInternal as $folderField) {
            if (null === $folderField->getValue()) {
                $folderField->setValue($folderField->getDefaultValue());
                $this->addStatusMessage(sprintf(_('%s prefilled from default'), $folderField->getCode()));
            }
        }
    }

    /**
     * Choose one of provided fields.
     *
     * @param array<string, string> $properties
     */
    public function providedFieldsSelect(string $name, string $defaultValue = '', array $properties = []): \Ease\Html\SelectTag
    {
        $items = ['' => _('Manually entered value')];

        foreach ($this->configFieldsProvided as $configField) {
            $items[$configField->getCode()] = $configField->getName();
        }

        return new \Ease\Html\SelectTag($name, $items, $defaultValue, $properties);
    }

    public function takeData($data): int
    {
        $imported = 0;

        foreach ($data as $key => $fieldData) {
            $field = $this->configFieldsInternal->getFieldByCode($key);

            if ($field) {
                $field->setValue(\is_string($fieldData) ? $fieldData : $fieldData['value']);
                ++$imported;
            } else {
                $this->setDataValue($key, $fieldData);
            }
        }

        return $imported;
    }

    public function fieldsProvided(): \MultiFlexi\ConfigFields
    {
        return $this->configFieldsProvided;
    }

    public function fieldsInternal(): \MultiFlexi\ConfigFields
    {
        return $this->configFieldsInternal;
    }

    public function checkInternalFields()
    {
        return true;
    }

    public function checkProvidedFields()
    {
        return true;
    }

    public function query(): ConfigFields
    {
        return $this->fieldsProvided();
    }

    /**
     * Import JSON credential prototype definition.
     */
    public function importJson(array $jsonData): bool
    {
        $defaultLang = 'en';
        $translations = [];

        // Extract localized name
        $name = '';

        if (isset($jsonData['name'])) {
            if (\is_string($jsonData['name'])) {
                $name = $jsonData['name'];
            } elseif (\is_array($jsonData['name'])) {
                $name = $jsonData['name'][$defaultLang] ?? reset($jsonData['name']);

                foreach ($jsonData['name'] as $lang => $value) {
                    $translations[$lang]['name'] = $value;
                }
            }
        }

        // Extract localized description
        $description = '';

        if (isset($jsonData['description'])) {
            if (\is_string($jsonData['description'])) {
                $description = $jsonData['description'];
            } elseif (\is_array($jsonData['description'])) {
                $description = $jsonData['description'][$defaultLang] ?? reset($jsonData['description']);

                foreach ($jsonData['description'] as $lang => $value) {
                    $translations[$lang]['description'] = $value;
                }
            }
        }

        // Check if prototype already exists by UUID or code
        $existing = null;

        if (!empty($jsonData['uuid'])) {
            $existing = $this->getFluentPDO()
                ->from($this->myTable)
                ->where('uuid', $jsonData['uuid'])
                ->fetch();
        }

        if (!$existing && !empty($jsonData['code'])) {
            $existing = $this->getFluentPDO()
                ->from($this->myTable)
                ->where('code', $jsonData['code'])
                ->fetch();
        }

        $protoData = [
            'uuid' => $jsonData['uuid'] ?? '',
            'code' => $jsonData['code'] ?? '',
            'name' => $name,
            'description' => $description,
            'version' => $jsonData['version'] ?? '1.0',
            'logo' => $jsonData['logo'] ?? '',
            'url' => $jsonData['url'] ?? '',
        ];

        if (isset($jsonData['user'])) {
            $protoData['user'] = (int) $jsonData['user'];
        }

        if ($existing) {
            $this->setMyKey($existing['id']);
            $this->takeData($protoData);
            $this->saveToSQL();
        } else {
            $this->takeData($protoData);
            $this->saveToSQL();
        }

        $protoId = $this->getMyKey();

        if (!$protoId) {
            return false;
        }

        // Save translations
        foreach ($translations as $lang => $data) {
            $existingTrans = $this->getFluentPDO()
                ->from('credential_prototype_translations')
                ->where('credential_prototype_id', $protoId)
                ->where('lang', $lang)
                ->fetch();

            if ($existingTrans) {
                $this->getFluentPDO()
                    ->update('credential_prototype_translations')
                    ->set($data)
                    ->where('credential_prototype_id', $protoId)
                    ->where('lang', $lang)
                    ->execute();
            } else {
                $this->getFluentPDO()
                    ->insertInto('credential_prototype_translations', array_merge($data, [
                        'credential_prototype_id' => $protoId,
                        'lang' => $lang,
                    ]))
                    ->execute();
            }
        }

        // Import fields
        if (isset($jsonData['fields']) && \is_array($jsonData['fields'])) {
            // Clear existing fields
            $this->getFluentPDO()
                ->deleteFrom('credential_prototype_field')
                ->where('credential_prototype_id', $protoId)
                ->execute();

            foreach ($jsonData['fields'] as $fieldData) {
                $fieldTranslations = [];

                // Handle localized field name
                $fieldName = '';

                if (isset($fieldData['name'])) {
                    if (\is_string($fieldData['name'])) {
                        $fieldName = $fieldData['name'];
                    } elseif (\is_array($fieldData['name'])) {
                        $fieldName = $fieldData['name'][$defaultLang] ?? reset($fieldData['name']);

                        foreach ($fieldData['name'] as $lang => $value) {
                            $fieldTranslations[$lang]['name'] = $value;
                        }
                    }
                }

                // Handle localized field description
                $fieldDescription = '';

                if (isset($fieldData['description'])) {
                    if (\is_string($fieldData['description'])) {
                        $fieldDescription = $fieldData['description'];
                    } elseif (\is_array($fieldData['description'])) {
                        $fieldDescription = $fieldData['description'][$defaultLang] ?? reset($fieldData['description']);

                        foreach ($fieldData['description'] as $lang => $value) {
                            $fieldTranslations[$lang]['description'] = $value;
                        }
                    }
                }

                $fieldRecord = [
                    'credential_prototype_id' => $protoId,
                    'keyword' => $fieldData['keyword'] ?? '',
                    'type' => $fieldData['type'] ?? 'string',
                    'name' => $fieldName,
                    'description' => $fieldDescription,
                    'hint' => $fieldData['hint'] ?? '',
                    'default_value' => (string) ($fieldData['default'] ?? $fieldData['default_value'] ?? ''),
                    'required' => isset($fieldData['required']) ? (int) $fieldData['required'] : 0,
                    'options' => isset($fieldData['options']) ? json_encode($fieldData['options']) : null,
                ];

                $fieldId = $this->getFluentPDO()
                    ->insertInto('credential_prototype_field', $fieldRecord)
                    ->execute();

                // Save field translations
                foreach ($fieldTranslations as $lang => $ftData) {
                    $this->getFluentPDO()
                        ->insertInto('credential_prototype_field_translations', array_merge($ftData, [
                            'credential_prototype_field_id' => $fieldId,
                            'lang' => $lang,
                        ]))
                        ->execute();
                }
            }
        }

        return true;
    }

    /**
     * Export credential prototype to JSON format.
     */
    public function exportJson(): array
    {
        $protoId = $this->getMyKey();
        $export = [];

        $export['schema'] = 'https://raw.githubusercontent.com/VitexSoftware/php-vitexsoftware-multiflexi-core/refs/heads/main/schema/credential-prototype.json';
        $export['version'] = $this->getDataValue('version') ?? '1.0';
        $export['uuid'] = $this->getDataValue('uuid') ?? '';
        $export['code'] = $this->getDataValue('code') ?? '';

        // Localized name and description
        $localizedFields = ['name', 'description'];

        if ($protoId) {
            $translations = $this->getFluentPDO()
                ->from('credential_prototype_translations')
                ->where('credential_prototype_id', $protoId)
                ->fetchAll();
        } else {
            $translations = [];
        }

        foreach ($localizedFields as $field) {
            if (!empty($translations)) {
                $localized = [];

                foreach ($translations as $tr) {
                    if (!empty($tr[$field])) {
                        $localized[$tr['lang']] = $tr[$field];
                    }
                }

                if (\count($localized) > 0) {
                    $export[$field] = $localized;
                } else {
                    $export[$field] = $this->getDataValue($field) ?? '';
                }
            } else {
                $export[$field] = $this->getDataValue($field) ?? '';
            }
        }

        $url = $this->getDataValue('url');

        if (!empty($url)) {
            $export['url'] = $url;
        }

        $logo = $this->getDataValue('logo');

        if (!empty($logo)) {
            $export['logo'] = $logo;
        }

        // Export fields
        $export['fields'] = [];

        if ($protoId) {
            $fieldRows = $this->getFluentPDO()
                ->from('credential_prototype_field')
                ->where('credential_prototype_id', $protoId)
                ->fetchAll();

            foreach ($fieldRows as $row) {
                $fieldExport = [
                    'keyword' => $row['keyword'],
                ];

                // Get field translations
                $fieldTranslations = $this->getFluentPDO()
                    ->from('credential_prototype_field_translations')
                    ->where('credential_prototype_field_id', $row['id'])
                    ->fetchAll();

                // Localized field name
                if (!empty($fieldTranslations)) {
                    $names = [];
                    $descs = [];

                    foreach ($fieldTranslations as $ft) {
                        if (!empty($ft['name'])) {
                            $names[$ft['lang']] = $ft['name'];
                        }

                        if (!empty($ft['description'])) {
                            $descs[$ft['lang']] = $ft['description'];
                        }
                    }

                    $fieldExport['name'] = \count($names) > 0 ? $names : ($row['name'] ?? '');
                    $fieldExport['type'] = $row['type'];
                    $fieldExport['description'] = \count($descs) > 0 ? $descs : ($row['description'] ?? '');
                } else {
                    $fieldExport['name'] = $row['name'] ?? '';
                    $fieldExport['type'] = $row['type'];
                    $fieldExport['description'] = $row['description'] ?? '';
                }

                $fieldExport['required'] = (bool) $row['required'];

                if (!empty($row['default_value'])) {
                    $fieldExport['default'] = $row['default_value'];
                }

                if (!empty($row['hint'])) {
                    $fieldExport['hint'] = $row['hint'];
                }

                if (!empty($row['options'])) {
                    $fieldExport['options'] = json_decode($row['options'], true);
                }

                $export['fields'][] = $fieldExport;
            }
        }

        return $export;
    }

    /**
     * Validate code format.
     */
    public function validateCodeFormat(string $code): bool
    {
        return preg_match('/^[a-zA-Z0-9_-]{2,64}$/', $code) === 1;
    }

    /**
     * Validate UUID format.
     */
    public function validateUuidFormat(string $uuid): bool
    {
        return preg_match('/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/', $uuid) === 1;
    }
}
