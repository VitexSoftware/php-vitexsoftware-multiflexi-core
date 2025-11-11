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

class Credential extends DBEngine
{
    /**
     * @var string Name Column
     */
    public string $nameColumn = 'name';
    private Credata $credator;
    private CredentialConfigFields $vault;
    private ?CredentialType $credentialType = null;
    private ConfigFields $fields;

    public function __construct($identifier = null, $options = [])
    {
        $this->myTable = 'credentials';
        $this->keyColumn = 'id';
        $this->credator = new Credata();
        $this->vault = new CredentialConfigFields($this);
        $this->fields = new ConfigFields(\Ease\Functions::baseClassName($this));
        parent::__construct($identifier, $options);
    }

    public function __unserialize(array $data): void
    {
        if (\array_key_exists('data', $data) && \is_array($data['data'])) {
            $this->takeData($data['data']);
        }
    }

    /**
     * Set assigned CredentialType object.
     */
    public function setCredentialType(?CredentialType $credentialType): self
    {
        $this->credentialType = $credentialType;

        return $this;
    }

    /**
     * Get assigned CredentialType object.
     */
    public function getCredentialType(): ?CredentialType
    {
        return $this->credentialType;
    }

    public function takeData(array $data): int
    {
        if (\array_key_exists('name', $data) === false || empty($data['name'])) {
            if (\array_key_exists('company_id', $data) && $data['company_id']) {
                $companer = new Company((int) $data['company_id']);

                $data['name'] = $companer->getRecordName();
            }
        }

        if (empty($data['id'])) {
            unset($data['id']);
        }

        if (\array_key_exists('credential_type_id', $data)) {
            $this->setCredentialType(new CredentialType((int) $data['credential_type_id']));
        }

        return parent::takeData($data);
    }

    public function insertToSQL($data = null): int
    {
        if (null === $data) {
            $data = $this->getData();
        }

        $fieldData = [];

        $credType = new \MultiFlexi\CredentialType((int) $data['credential_type_id']);
        $fields = $credType->getFields();

        foreach ($data as $columName => $value) {
            if ($fields->getFieldByCode($columName)) {
                \Ease\Functions::divDataArray($data, $fieldData, $columName);
            }
        }

        $recordId = parent::insertToSQL($data);

        if ($fieldData) {
            foreach ($fieldData as $filedName => $fieldValue) {
                $this->credator->insertToSQL(
                    [
                        'credential_id' => $recordId,
                        'name' => $filedName,
                        'value' => $fieldValue,
                        'type' => $fields->getFieldByCode($filedName)->getType(),
                    ],
                );
            }
        }

        return $recordId;
    }

    public function updateToSQL($data = null, $conditons = []): int
    {
        if (null === $data) {
            $data = $this->getData();
        }

        unset($data['csrf_token']);
        $originalData = $data;

        $fieldData = [];

        $credType = new \MultiFlexi\CredentialType((int) $data['credential_type_id']);
        $fields = $credType->getFields();

        foreach ($data as $columName => $value) {
            if ($fields->getFieldByCode($columName)) {
                \Ease\Functions::divDataArray($data, $fieldData, $columName);
            }
        }

        $currentData = $this->credator->listingQuery()->where('credential_id', $this->getMyKey())->fetchAll('name');

        foreach (\array_keys($fieldData) as $field) {
            if (\array_key_exists($field, $currentData)) {
                $this->credator->updateToSQL(
                    ['value' => $fieldData[$field]],
                    [
                        'credential_id' => $this->getMyKey(),
                        'name' => $field,
                    ],
                );
            } else {
                $this->credator->insertToSQL(
                    ['value' => $fieldData[$field],
                        'credential_id' => $this->getMyKey(),
                        'name' => $field,
                        'type' => $fields->getFieldByCode($field)->getType(),
                    ],
                );
            }

            unset($originalData[$field]); // Processed field data
        }

        $this->takeData($originalData);

        return parent::updateToSQL($data, $conditons);
    }

    public function loadFromSQL($itemID = null)
    {
        if (null === $itemID) {
            $itemID = $this->getMyKey();
        }

        $dataCount = parent::loadFromSQL($itemID);

        if ($this->getRecordName()) {
            $this->fields->setName($this->getRecordName());
        }

        if ($this->credentialType && $this->credentialType->getHelper()) {
            $this->fields->addFields($this->credentialType->getHelper()->fieldsProvided());
        }

        foreach ($this->credator->listingQuery()->where('credential_id', $this->getMyKey()) as $credential) {
            if ($this->fields->getFieldByCode($credential['name'])) {
                $this->fields->getFieldByCode($credential['name'])->setValue($credential['value']);
            } else {
                $this->fields->addField(new ConfigField($credential['name'], $credential['value'], $credential['type']));
            }

            $this->setDataValue($credential['name'], $credential['value']);
            ++$dataCount;
        }

        return $dataCount;
    }

    public function deleteFromSQL($data = null)
    {
        $this->credator->deleteFromSQL(['credential_id' => $this->getMyKey()]);

        return parent::deleteFromSQL($data);
    }

    public function getCompanyCredentials(int $companyId, $appRequirements = []): array
    {
        $companyCredentials = $this->listingQuery()->where('company_id', $companyId);

        foreach ($appRequirements as $req) {
            $companyCredentials->whereOr('formType', $req);
        }

        return $companyCredentials->fetchAll('id');
    }

    /**
     * Return Credential and its CredentialType environment.
     */
    public function query(): CredentialConfigFields
    {
        $credentialEnv = new CredentialConfigFields($this);

        if ($this->getCredentialType() && $this->credentialType->getHelper()) {
            $credentialEnv->addFields($this->credentialType->getHelper()->query());
        }

        // Load Credential values stored in database
        foreach ($this->credator->listingQuery()->where('credential_id', $this->getMyKey()) as $credential) {
            $fieldProvidedByCredType = $credentialEnv->getFieldByCode($credential['name']);

            if (\is_object($fieldProvidedByCredType)) {
                $fieldProvidedByCredType->setValue((string) $credential['value']);
                $fieldProvidedByCredType->setSource(serialize($this));
            } else {
                $field = new ConfigField($credential['name'], $credential['type'], $credential['name'], '', '', $credential['value']);
                $field->setSource(serialize($this));
                $credentialEnv->addField($field);
            }

            $this->setDataValue($credential['name'], $credential['value']);
        }

        $this->vault->addFields($credentialEnv);

        return $credentialEnv;
    }

    public function getFields(): ConfigFields
    {
        return $this->fields;
    }
}
