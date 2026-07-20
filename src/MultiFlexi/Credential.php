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

    /**
     * Named key used to encrypt/decrypt credential field values at rest.
     */
    private const ENCRYPTION_KEY_NAME = 'credentials';

    public function __construct($identifier = null, $options = [])
    {
        $this->myTable = 'credentials';
        $this->keyColumn = 'id';
        $this->credator = new Credata();
        $this->vault = new CredentialConfigFields($this);
        $this->fields = new ConfigFields(\Ease\Functions::baseClassName($this));
        parent::__construct($identifier, $options);
    }

    /**
     * Obtain a DataEncryption instance, or null when encryption at rest is
     * disabled via DATA_ENCRYPTION_ENABLED=false.
     *
     * Deliberately uses a throwaway Credata instance for its PDO connection
     * rather than $this->credator or $this: Ease\SQL\Orm::getFluentPDO()
     * caches its FluentPDO\Query (and the connection captured inside it)
     * the first time it's called on an object, but a direct getPdo() call
     * on that same object always opens ANOTHER fresh connection when
     * DB_PERSISTENT is off (the case on SQLite installs) — leaving that
     * extra connection permanently parked in the object's $pdo property,
     * alongside the different connection $fluent actually uses for
     * inserts/updates. Two simultaneously open connections to one SQLite
     * file then deadlock with "database is locked" as soon as one writes.
     * A fresh, local Credata() has no cached $fluent yet and isn't kept
     * anywhere after this method returns, so its connection is opened and
     * closed cleanly within this call, never overlapping with credator's.
     */
    private function getEncryptor(): ?\MultiFlexi\Security\DataEncryption
    {
        if (!\Ease\Shared::cfg('DATA_ENCRYPTION_ENABLED', true)) {
            return null;
        }

        return new \MultiFlexi\Security\DataEncryption((new Credata())->getPdo());
    }

    /**
     * Retry a SQL write a few times on SQLite's "database is locked".
     *
     * Ease\SQL\Orm opens a fresh connection per getPdo() call whenever
     * DB_PERSISTENT is off (SQLite installs), and none of those
     * connections have a busy_timeout configured — so any write that
     * overlaps another connection's write (e.g. this class's own
     * encryption-key lookup, or the framework's SQL-backed logger)
     * fails immediately with SQLSTATE[HY000] error 5 instead of
     * waiting. A short randomized-backoff retry absorbs that instead
     * of surfacing a spurious failure to the caller.
     */
    private function retryOnLock(callable $write): mixed
    {
        $attempts = 0;

        while (true) {
            try {
                return $write();
            } catch (\PDOException $e) {
                ++$attempts;

                if ($attempts >= 5 || !str_contains($e->getMessage(), 'database is locked')) {
                    throw $e;
                }

                usleep(random_int(20000, 80000) * $attempts);
            }
        }
    }

    /**
     * Encrypt a redactable field value for storage in credata.value.
     *
     * Fails closed: when encryption is required (DATA_ENCRYPTION_ENABLED,
     * the default) but unavailable (e.g. `encryption:init` was never run),
     * this throws rather than silently persisting the secret in plaintext.
     *
     * @return array{value: string, is_encrypted: int, encryption_key_version: ?int}
     */
    private function encryptFieldValue(string $fieldName, string $plaintext): array
    {
        $encryptor = $this->getEncryptor();

        if ($encryptor === null) {
            return ['value' => $plaintext, 'is_encrypted' => 0, 'encryption_key_version' => null];
        }

        $envelope = $encryptor->encrypt($plaintext, self::ENCRYPTION_KEY_NAME);

        // Force the throwaway Credata/DataEncryption instance's SQLite
        // connection to close now rather than whenever PHP's cycle
        // collector next happens to run: confirmed live on 10.11.182.73
        // that without this, the connection can still be open when this
        // object's OWN write (e.g. the parent::updateToSQL() at the end of
        // updateToSQL()) runs moments later, failing with "database is
        // locked" even though nothing still needs that connection.
        unset($encryptor);
        gc_collect_cycles();

        return [
            'value' => json_encode($envelope, \JSON_THROW_ON_ERROR),
            'is_encrypted' => 1,
            'encryption_key_version' => $envelope['key_version'] ?? null,
        ];
    }

    /**
     * Decrypt a value read from credata.value back to plaintext, or return
     * it unchanged when it isn't marked as encrypted. On decryption
     * failure (e.g. missing/rotated-away key), logs a status message and
     * returns the raw (still-encrypted, unusable) value rather than fatal.
     */
    private function decryptFieldValue(string $fieldName, ?string $storedValue, bool $isEncrypted): ?string
    {
        if (!$isEncrypted || $storedValue === null || $storedValue === '') {
            return $storedValue;
        }

        $encryptor = $this->getEncryptor();

        if ($encryptor === null) {
            $this->addStatusMessage(sprintf(_('Cannot decrypt field %s: data encryption is disabled'), $fieldName), 'error');

            return $storedValue;
        }

        try {
            $envelope = json_decode($storedValue, true, 512, \JSON_THROW_ON_ERROR);

            return $encryptor->decrypt($envelope);
        } catch (\Throwable $e) {
            $this->addStatusMessage(sprintf(_('Failed to decrypt field %s: %s'), $fieldName, $e->getMessage()), 'error');

            return $storedValue;
        } finally {
            // See encryptFieldValue(): the throwaway Credata/DataEncryption
            // instance's SQLite connection can otherwise survive past this
            // method's return (PHP's cycle collector isn't guaranteed to run
            // immediately), lingering long enough to make a subsequent write
            // on this object's own connection fail with "database is locked".
            unset($encryptor);
            gc_collect_cycles();
        }
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

        $recordId = $this->retryOnLock(fn () => parent::insertToSQL($data));

        if ($fieldData) {
            foreach ($fieldData as $filedName => $fieldValue) {
                $field = $fields->getFieldByCode($filedName);
                $storage = ['value' => $fieldValue, 'is_encrypted' => 0, 'encryption_key_version' => null];

                if ($field->isRedactable() && $fieldValue !== null && $fieldValue !== '') {
                    $storage = $this->encryptFieldValue($filedName, (string) $fieldValue);
                }

                $this->retryOnLock(fn () => $this->credator->insertToSQL([
                    'credential_id' => $recordId,
                    'name' => $filedName,
                    'value' => $storage['value'],
                    'type' => $field->getType(),
                    'is_encrypted' => $storage['is_encrypted'],
                    'encryption_key_version' => $storage['encryption_key_version'],
                ]));
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
            $fieldDef = $fields->getFieldByCode($field);
            $isRecordable = \array_key_exists($field, $currentData);

            // A blank value on an already-stored redactable field means
            // "leave unchanged" (the edit-to-overwrite UI pattern) rather
            // than "erase the secret" — matches RuntemplateConfigForm's
            // "leave blank to keep the existing value" convention.
            if ($fieldDef->isRedactable() && $isRecordable && ($fieldData[$field] === null || $fieldData[$field] === '')) {
                unset($originalData[$field]);

                continue;
            }

            $storage = ['value' => $fieldData[$field], 'is_encrypted' => 0, 'encryption_key_version' => null];

            if ($fieldDef->isRedactable() && $fieldData[$field] !== null && $fieldData[$field] !== '') {
                $storage = $this->encryptFieldValue($field, (string) $fieldData[$field]);
            }

            if ($isRecordable) {
                $this->retryOnLock(fn () => $this->credator->updateToSQL(
                    [
                        'value' => $storage['value'],
                        'is_encrypted' => $storage['is_encrypted'],
                        'encryption_key_version' => $storage['encryption_key_version'],
                    ],
                    [
                        'credential_id' => $this->getMyKey(),
                        'name' => $field,
                    ],
                ));
            } else {
                $this->retryOnLock(fn () => $this->credator->insertToSQL([
                    'value' => $storage['value'],
                    'credential_id' => $this->getMyKey(),
                    'name' => $field,
                    'type' => $fieldDef->getType(),
                    'is_encrypted' => $storage['is_encrypted'],
                    'encryption_key_version' => $storage['encryption_key_version'],
                ]));
            }

            unset($originalData[$field]); // Processed field data
        }

        $this->takeData($originalData);

        return $this->retryOnLock(fn () => parent::updateToSQL($data, $conditons));
    }

    public function loadFromSQL($itemID = null)
    {
        if (null === $itemID) {
            $itemID = $this->getMyKey();
        }

        $dataCount = parent::loadFromSQL($itemID);

        if ($this->getMyKey()) {
            $this->fields->setSources(\Ease\Euri::fromObject($this));
        }

        if ($this->credentialType && $this->credentialType->getPrototype()) {
            $this->fields->addFields($this->credentialType->getPrototype()->fieldsProvided());
        }

        foreach ($this->credator->listingQuery()->where('credential_id', $this->getMyKey()) as $credential) {
            $value = $this->decryptFieldValue($credential['name'], $credential['value'], !empty($credential['is_encrypted']));

            if ($this->fields->getFieldByCode($credential['name'])) {
                $this->fields->getFieldByCode($credential['name'])->setValue($value);
            } else {
                $this->fields->addField(new ConfigField($credential['name'], $credential['type'], $credential['name'], _('undescribed custom field'), '', $value, $credential['type']));
                $this->addStatusMessage(sprintf(_('Config field %s inconsistency'), $credential['name']), 'warning');
            }

            $this->setDataValue($credential['name'], $value);
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

        if ($this->getCredentialType() && $this->credentialType->getPrototype()) {
            $credentialEnv->addFields($this->credentialType->getPrototype()->query());
        }

        // Load Credential values stored in database
        foreach ($this->credator->listingQuery()->where('credential_id', $this->getMyKey()) as $credential) {
            $value = $this->decryptFieldValue($credential['name'], $credential['value'], !empty($credential['is_encrypted']));
            $fieldProvidedByCredType = $credentialEnv->getFieldByCode($credential['name']);

            if (\is_object($fieldProvidedByCredType)) {
                if (empty($fieldProvidedByCredType->getValue())) {
                    $fieldProvidedByCredType->setValue((string) $value);
                    $fieldProvidedByCredType->setSource(\Ease\Euri::fromObject($this));
                }
            } else {
                $field = new ConfigField($credential['name'], $credential['type'], $credential['name'], '', '', $value);
                $field->setSource(\Ease\Euri::fromObject($this));
                $credentialEnv->addField($field);
            }

            $this->setDataValue($credential['name'], $value);
        }

        $this->vault->addFields($credentialEnv);

        return $credentialEnv;
    }

    public function getFields(): ConfigFields
    {
        return $this->fields;
    }

    /**
     * Data safe to expose to UI/API/CLI: identical to getData() except any
     * key that corresponds to a redactable (secret/password) field is
     * replaced by a masked placeholder instead of the real value.
     *
     * @return array<string, mixed>
     */
    public function getRedactedData(): array
    {
        $data = $this->getData();

        foreach ($this->getFields()->getFields() as $code => $field) {
            if (\array_key_exists($code, $data) && $field->isRedactable()) {
                $data[$code] = ConfigField::maskValue(\is_string($data[$code]) ? $data[$code] : (string) $data[$code]);
            }
        }

        return $data;
    }
}
