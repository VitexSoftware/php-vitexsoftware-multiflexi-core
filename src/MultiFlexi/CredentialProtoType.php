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
class CredentialProtoType extends \MultiFlexi\DBEngine
{
    use \Ease\recordkey;

    /**
     * Database table name.
     */
    public string $myTable = 'credential_prototype';

    /**
     * Schema path for credential prototype JSON validation.
     */
    public static string $credProtoTypeSchema = __DIR__.'/../../schema/credential-prototype.json';
    protected \MultiFlexi\ConfigFields $configFieldsProvided;
    protected \MultiFlexi\ConfigFields $configFieldsInternal;

    public function __construct($init = null)
    {
        $this->configFieldsProvided = new \MultiFlexi\ConfigFields();
        $this->configFieldsInternal = new \MultiFlexi\ConfigFields();
        parent::__construct($init);
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
        // Set basic prototype data
        $this->setData([
            'uuid' => $jsonData['uuid'] ?? '',
            'code' => $jsonData['code'] ?? '',
            'name' => $jsonData['name'] ?? '',
            'description' => $jsonData['description'] ?? '',
            'version' => $jsonData['version'] ?? '1.0',
            'logo' => $jsonData['logo'] ?? '',
            'url' => $jsonData['url'] ?? '',
        ]);

        $saved = $this->saveToSQL();

        if ($saved && isset($jsonData['fields']) && \is_array($jsonData['fields'])) {
            $fielder = new \MultiFlexi\CredentialProtoTypeField();

            // Clear existing fields for this prototype
            $fielder->deleteFromSQL(['credential_prototype_id' => $this->getMyKey()]);

            // Import fields
            foreach ($jsonData['fields'] as $fieldData) {
                $fielder->dataReset();
                $fielder->setData([
                    'credential_prototype_id' => $this->getMyKey(),
                    'keyword' => $fieldData['keyword'] ?? '',
                    'type' => $fieldData['type'] ?? 'string',
                    'name' => $fieldData['name'] ?? $fieldData['keyword'],
                    'description' => $fieldData['description'] ?? '',
                    'hint' => $fieldData['hint'] ?? '',
                    'default_value' => $fieldData['default_value'] ?? '',
                    'required' => $fieldData['required'] ?? false,
                    'options' => isset($fieldData['options']) ? json_encode($fieldData['options']) : null,
                ]);
                $fielder->saveToSQL();
            }
        }

        return $saved !== false;
    }

    /**
     * Export credential prototype to JSON format.
     */
    public function exportJson(): array
    {
        $data = [
            'uuid' => $this->getDataValue('uuid'),
            'code' => $this->getDataValue('code'),
            'name' => $this->getDataValue('name'),
            'description' => $this->getDataValue('description'),
            'version' => $this->getDataValue('version'),
            'logo' => $this->getDataValue('logo'),
            'url' => $this->getDataValue('url'),
            'fields' => [],
        ];

        // Export fields
        if ($this->getMyKey()) {
            $fielder = new \MultiFlexi\CredentialProtoTypeField();
            $fields = $fielder->listingQuery()
                ->where('credential_prototype_id', $this->getMyKey())
                ->fetchAll();

            foreach ($fields as $field) {
                $fieldData = [
                    'keyword' => $field['keyword'],
                    'type' => $field['type'],
                    'name' => $field['name'],
                    'description' => $field['description'],
                    'required' => (bool) $field['required'],
                ];

                if (!empty($field['hint'])) {
                    $fieldData['hint'] = $field['hint'];
                }

                if (!empty($field['default_value'])) {
                    $fieldData['default_value'] = $field['default_value'];
                }

                if (!empty($field['options'])) {
                    $fieldData['options'] = json_decode($field['options'], true);
                }

                $data['fields'][] = $fieldData;
            }
        }

        return $data;
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
