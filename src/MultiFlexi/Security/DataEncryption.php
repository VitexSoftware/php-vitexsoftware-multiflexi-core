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

namespace MultiFlexi\Security;

/**
 * Data encryption manager for sensitive data at rest using AES-256-GCM.
 *
 * @author Vítězslav Dvořák <info@vitexsoftware.cz>
 */
class DataEncryption
{
    public const ALGORITHM_AES_256_GCM = 'AES-256-GCM';
    public const ALGORITHM_AES_256_CBC = 'AES-256-CBC';
    private \PDO $pdo;
    private string $keysTableName;
    private array $encryptionKeys = [];
    private string $defaultAlgorithm;

    public function __construct(
        \PDO $pdo,
        string $keysTableName = 'encryption_keys',
        string $defaultAlgorithm = self::ALGORITHM_AES_256_GCM,
    ) {
        $this->pdo = $pdo;
        $this->keysTableName = $keysTableName;
        $this->defaultAlgorithm = $defaultAlgorithm;
    }

    /**
     * Encrypt sensitive data.
     *
     * @param string      $plaintext Data to encrypt
     * @param string      $keyName   Name of the encryption key to use
     * @param null|string $algorithm Encryption algorithm (optional)
     *
     * @return array Encrypted data with metadata
     */
    public function encrypt(string $plaintext, string $keyName = 'default', ?string $algorithm = null): array
    {
        if (empty($plaintext)) {
            throw new \InvalidArgumentException('Cannot encrypt empty data');
        }

        $algorithm ??= $this->defaultAlgorithm;
        [$key, $keyVersion] = $this->getActiveEncryptionKey($keyName);

        if (!$key) {
            throw new \RuntimeException("Encryption key '{$keyName}' not found");
        }

        switch ($algorithm) {
            case self::ALGORITHM_AES_256_GCM:
                $envelope = self::encryptAesGcm($plaintext, $key, $keyName);

                break;
            case self::ALGORITHM_AES_256_CBC:
                $envelope = self::encryptAesCbc($plaintext, $key, $keyName);

                break;

            default:
                throw new \InvalidArgumentException("Unsupported encryption algorithm: {$algorithm}");
        }

        $envelope['key_version'] = $keyVersion;

        return $envelope;
    }

    /**
     * Decrypt sensitive data.
     *
     * @param array $encryptedData Encrypted data with metadata
     *
     * @return string Decrypted plaintext
     */
    public function decrypt(array $encryptedData): string
    {
        if (!isset($encryptedData['ciphertext'], $encryptedData['key_name'], $encryptedData['algorithm'])) {
            throw new \InvalidArgumentException('Invalid encrypted data format');
        }

        // Decryption must use the exact key version that produced the
        // ciphertext, regardless of whether that version is still the
        // active one (rotation deactivates old versions but keeps them
        // decryptable).
        $key = $this->getEncryptionKeyByVersion($encryptedData['key_name'], (int) ($encryptedData['key_version'] ?? 1));

        if (!$key) {
            throw new \RuntimeException("Encryption key '{$encryptedData['key_name']}' (version {$encryptedData['key_version']}) not found");
        }

        switch ($encryptedData['algorithm']) {
            case self::ALGORITHM_AES_256_GCM:
                return self::decryptAesGcm($encryptedData, $key);
            case self::ALGORITHM_AES_256_CBC:
                return self::decryptAesCbc($encryptedData, $key);

            default:
                throw new \InvalidArgumentException("Unsupported decryption algorithm: {$encryptedData['algorithm']}");
        }
    }

    /**
     * Generate a new encryption key.
     *
     * If a key with this name already has an active version, that version
     * is deactivated (its key_data is kept, so data encrypted under it
     * remains decryptable) and a new, higher-versioned row becomes active.
     * This is deliberate: overwriting key_data in place (the previous
     * behaviour) would permanently destroy the ability to decrypt any data
     * encrypted under the key being "rotated".
     *
     * @param string $keyName   Name for the new key
     * @param string $algorithm Algorithm the key will be used for
     */
    public function generateKey(string $keyName, string $algorithm = self::ALGORITHM_AES_256_GCM): bool
    {
        // Generate random key
        $key = random_bytes(32); // 256-bit key

        // Encrypt the key before storing
        $encryptedKey = self::encryptStoredKey($key);

        $nextVersionStmt = $this->pdo->prepare(
            "SELECT COALESCE(MAX(version), 0) + 1 FROM `{$this->keysTableName}` WHERE key_name = ?",
        );
        $nextVersionStmt->execute([$keyName]);
        $nextVersion = (int) $nextVersionStmt->fetchColumn();

        $this->pdo->beginTransaction();

        try {
            $deactivateStmt = $this->pdo->prepare(
                "UPDATE `{$this->keysTableName}` SET is_active = FALSE, rotated_at = NOW() WHERE key_name = ? AND is_active = TRUE",
            );
            $deactivateStmt->execute([$keyName]);

            $insertStmt = $this->pdo->prepare(
                "INSERT INTO `{$this->keysTableName}` (key_name, version, key_data, algorithm, created_at, is_active) VALUES (?, ?, ?, ?, NOW(), TRUE)",
            );
            $success = $insertStmt->execute([$keyName, $nextVersion, $encryptedKey, $algorithm]);

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();

            throw $e;
        }

        if ($success) {
            // Clear cache to force reload
            unset($this->encryptionKeys[$keyName.'#active']);

            // Log key generation/rotation
            if (isset($GLOBALS['securityAuditLogger'])) {
                $GLOBALS['securityAuditLogger']->logEvent(
                    'encryption_key_generated',
                    "Encryption key generated/rotated: {$keyName} (version {$nextVersion})",
                    'high',
                    null,
                    ['key_name' => $keyName, 'version' => $nextVersion, 'algorithm' => $algorithm],
                );
            }
        }

        return $success;
    }

    /**
     * Rotate an encryption key: generate a new active version, keeping the
     * previous version's key_data intact for decrypting old ciphertext.
     */
    public function rotateKey(string $keyName): bool
    {
        // Get current algorithm
        $sql = "SELECT algorithm FROM `{$this->keysTableName}` WHERE key_name = ? AND is_active = TRUE";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$keyName]);
        $algorithm = $stmt->fetchColumn();

        if (!$algorithm) {
            throw new \RuntimeException("Key '{$keyName}' not found for rotation");
        }

        return $this->generateKey($keyName, $algorithm);
    }

    /**
     * List available encryption keys.
     */
    public function listKeys(): array
    {
        $sql = <<<EOD
SELECT key_name, version, algorithm, created_at, rotated_at, is_active
                FROM `{$this->keysTableName}`
                ORDER BY key_name, version DESC
EOD;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Deactivate the currently active version of an encryption key, without
     * generating a replacement. New data encrypted under this key name will
     * fail until generateKey()/rotateKey() is called again; existing
     * ciphertext remains decryptable since key_data is preserved.
     */
    public function deactivateKey(string $keyName): bool
    {
        $sql = "UPDATE `{$this->keysTableName}` SET is_active = FALSE WHERE key_name = ? AND is_active = TRUE";
        $stmt = $this->pdo->prepare($sql);
        $success = $stmt->execute([$keyName]);

        if ($success) {
            // Clear from cache
            unset($this->encryptionKeys[$keyName.'#active']);

            // Log key deactivation
            if (isset($GLOBALS['securityAuditLogger'])) {
                $GLOBALS['securityAuditLogger']->logEvent(
                    'encryption_key_deactivated',
                    "Encryption key deactivated: {$keyName}",
                    'medium',
                    null,
                    ['key_name' => $keyName],
                );
            }
        }

        return $success;
    }

    /**
     * Test encryption/decryption functionality.
     */
    public function test(string $keyName = 'default'): bool
    {
        try {
            $testData = 'Test encryption data: '.time();

            // Encrypt
            $encrypted = $this->encrypt($testData, $keyName);

            // Decrypt
            $decrypted = $this->decrypt($encrypted);

            return $testData === $decrypted;
        } catch (\Exception $e) {
            error_log('Encryption test failed: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Bulk encrypt data for migration.
     */
    public function bulkEncrypt(array $data, string $keyName = 'default'): array
    {
        $results = [];

        foreach ($data as $key => $value) {
            if (\is_string($value) && !empty($value)) {
                try {
                    $results[$key] = $this->encrypt($value, $keyName);
                } catch (\Exception $e) {
                    error_log("Failed to encrypt key '{$key}': ".$e->getMessage());
                    $results[$key] = null;
                }
            } else {
                $results[$key] = $value;
            }
        }

        return $results;
    }

    /**
     * Initialize default encryption keys.
     */
    public function initializeDefaultKeys(): void
    {
        $defaultKeys = [
            'default' => self::ALGORITHM_AES_256_GCM,
            'credentials' => self::ALGORITHM_AES_256_GCM,
            'personal_data' => self::ALGORITHM_AES_256_GCM,
        ];

        foreach ($defaultKeys as $keyName => $algorithm) {
            try {
                // Check if key exists
                [$existingKey] = $this->getActiveEncryptionKey($keyName);

                if (!$existingKey) {
                    $this->generateKey($keyName, $algorithm);
                }
            } catch (\Exception $e) {
                error_log("Failed to initialize key '{$keyName}': ".$e->getMessage());
            }
        }
    }

    /**
     * Encrypt using AES-256-GCM.
     */
    private static function encryptAesGcm(string $plaintext, string $key, string $keyName): array
    {
        $iv = random_bytes(12); // 96-bit IV for GCM
        $tag = '';

        $ciphertext = openssl_encrypt(
            $plaintext,
            'aes-256-gcm',
            $key,
            \OPENSSL_RAW_DATA,
            $iv,
            $tag,
        );

        if ($ciphertext === false) {
            throw new \RuntimeException('AES-GCM encryption failed: '.openssl_error_string());
        }

        return [
            'ciphertext' => base64_encode($ciphertext),
            'iv' => base64_encode($iv),
            'tag' => base64_encode($tag),
            'algorithm' => self::ALGORITHM_AES_256_GCM,
            'key_name' => $keyName,
            'encrypted_at' => time(),
        ];
    }

    /**
     * Decrypt using AES-256-GCM.
     */
    private static function decryptAesGcm(array $encryptedData, string $key): string
    {
        $plaintext = openssl_decrypt(
            base64_decode($encryptedData['ciphertext'], true),
            'aes-256-gcm',
            $key,
            \OPENSSL_RAW_DATA,
            base64_decode($encryptedData['iv'], true),
            base64_decode($encryptedData['tag'], true),
        );

        if ($plaintext === false) {
            throw new \RuntimeException('AES-GCM decryption failed: '.openssl_error_string());
        }

        return $plaintext;
    }

    /**
     * Encrypt using AES-256-CBC.
     */
    private static function encryptAesCbc(string $plaintext, string $key, string $keyName): array
    {
        $iv = random_bytes(16); // 128-bit IV for CBC

        $ciphertext = openssl_encrypt(
            $plaintext,
            'aes-256-cbc',
            $key,
            \OPENSSL_RAW_DATA,
            $iv,
        );

        if ($ciphertext === false) {
            throw new \RuntimeException('AES-CBC encryption failed: '.openssl_error_string());
        }

        return [
            'ciphertext' => base64_encode($ciphertext),
            'iv' => base64_encode($iv),
            'algorithm' => self::ALGORITHM_AES_256_CBC,
            'key_name' => $keyName,
            'encrypted_at' => time(),
        ];
    }

    /**
     * Decrypt using AES-256-CBC.
     */
    private static function decryptAesCbc(array $encryptedData, string $key): string
    {
        $plaintext = openssl_decrypt(
            base64_decode($encryptedData['ciphertext'], true),
            'aes-256-cbc',
            $key,
            \OPENSSL_RAW_DATA,
            base64_decode($encryptedData['iv'], true),
        );

        if ($plaintext === false) {
            throw new \RuntimeException('AES-CBC decryption failed: '.openssl_error_string());
        }

        return $plaintext;
    }

    /**
     * Resolve the key material to use for encrypting NEW data: always the
     * currently active version of the named key.
     *
     * @return array{0: ?string, 1: int} [decrypted key material or null, version]
     */
    private function getActiveEncryptionKey(string $keyName): array
    {
        $cacheKey = $keyName.'#active';

        if (isset($this->encryptionKeys[$cacheKey])) {
            return $this->encryptionKeys[$cacheKey];
        }

        $sql = "SELECT key_data, version FROM `{$this->keysTableName}` WHERE key_name = ? AND is_active = TRUE";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$keyName]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            return [null, 0];
        }

        $result = [self::decryptStoredKey($row['key_data']), (int) $row['version']];
        $this->encryptionKeys[$cacheKey] = $result;

        return $result;
    }

    /**
     * Resolve the key material for DECRYPTING existing ciphertext: the
     * exact (key_name, version) it was encrypted under, regardless of
     * whether that version is still active. Rotation deactivates old
     * versions but never deletes their key_data, so this always succeeds
     * for data encrypted by this same key store.
     */
    private function getEncryptionKeyByVersion(string $keyName, int $version): ?string
    {
        $cacheKey = $keyName.'#v'.$version;

        if (isset($this->encryptionKeys[$cacheKey])) {
            return $this->encryptionKeys[$cacheKey];
        }

        $sql = "SELECT key_data FROM `{$this->keysTableName}` WHERE key_name = ? AND version = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$keyName, $version]);

        $keyData = $stmt->fetchColumn();

        if (!$keyData) {
            return null;
        }

        $key = self::decryptStoredKey($keyData);
        $this->encryptionKeys[$cacheKey] = $key;

        return $key;
    }

    /**
     * Get master key for encrypting stored keys.
     */
    private static function getMasterKey(): string
    {
        // Try environment variable first
        $masterKey = getenv('ENCRYPTION_MASTER_KEY');

        if ($masterKey) {
            return hash('sha256', $masterKey, true);
        }

        // Try alternative environment variable name for backward compatibility
        $masterKey = getenv('MULTIFLEXI_MASTER_KEY');

        if ($masterKey) {
            return hash('sha256', $masterKey, true);
        }

        // Load from config (.env file)
        $masterKey = \Ease\Shared::cfg('ENCRYPTION_MASTER_KEY');

        if ($masterKey) {
            return hash('sha256', $masterKey, true);
        }

        throw new \RuntimeException('No encryption master key found. Set ENCRYPTION_MASTER_KEY in .env file or as environment variable.');
    }

    /**
     * Encrypt key for storage.
     */
    private static function encryptStoredKey(string $key): string
    {
        $masterKey = self::getMasterKey();
        $iv = random_bytes(16);

        $encrypted = openssl_encrypt($key, 'aes-256-cbc', $masterKey, \OPENSSL_RAW_DATA, $iv);

        if ($encrypted === false) {
            throw new \RuntimeException('Failed to encrypt storage key');
        }

        return base64_encode($iv.$encrypted);
    }

    /**
     * Decrypt stored key.
     */
    private static function decryptStoredKey(string $encryptedKey): string
    {
        $masterKey = self::getMasterKey();
        $data = base64_decode($encryptedKey, true);

        if ($data === false || \strlen($data) < 16) {
            throw new \RuntimeException('Invalid encrypted key data');
        }

        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);

        $key = openssl_decrypt($encrypted, 'aes-256-cbc', $masterKey, \OPENSSL_RAW_DATA, $iv);

        if ($key === false) {
            throw new \RuntimeException('Failed to decrypt storage key');
        }

        return $key;
    }
}
