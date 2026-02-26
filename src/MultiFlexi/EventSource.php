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
 * EventSource represents an external webhook adapter database to poll for changes.
 *
 * Each EventSource defines connection parameters to an adapter database
 * (e.g. abraflexi-webhook-acceptor) that contains a `changes_cache` table.
 *
 * @author Vítězslav Dvořák <info@vitexsoftware.cz>
 */
class EventSource extends DBEngine
{
    /**
     * @var string Name Column
     */
    public string $nameColumn = 'name';

    /**
     * @var string Create column name
     */
    public ?string $createColumn = 'created';

    /**
     * @var string Last modified column name
     */
    public ?string $lastModifiedColumn = 'modified';

    /**
     * @var \PDO|null Cached PDO connection to adapter database
     */
    private ?\PDO $adapterConnection = null;

    /**
     * EventSource constructor.
     *
     * @param int|null $identifier Record ID
     * @param array    $options    Additional options
     */
    public function __construct($identifier = null, $options = [])
    {
        $this->myTable = 'event_source';
        $this->keyColumn = 'id';
        parent::__construct($identifier, $options);
    }

    /**
     * Create a PDO connection to the adapter database.
     *
     * @return \PDO PDO connection to the external adapter database
     */
    public function getConnection(): \PDO
    {
        if ($this->adapterConnection !== null) {
            return $this->adapterConnection;
        }

        $driver = $this->getDataValue('db_connection') ?: 'mysql';
        $host = $this->getDataValue('db_host') ?: 'localhost';
        $port = $this->getDataValue('db_port') ?: '3306';
        $database = $this->getDataValue('db_database');
        $username = $this->getDataValue('db_username');
        $password = $this->getDataValue('db_password');

        switch ($driver) {
            case 'sqlite':
                $dsn = 'sqlite:'.$database;

                break;
            case 'pgsql':
                $dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', $host, $port, $database);

                break;
            default:
                $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $database);

                break;
        }

        $this->adapterConnection = new \PDO($dsn, $username, $password, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);

        return $this->adapterConnection;
    }

    /**
     * Fetch unprocessed changes from the adapter's changes_cache table.
     *
     * @return array<int, array<string, mixed>> Array of change records
     */
    public function getUnprocessedChanges(): array
    {
        $pdo = $this->getConnection();
        $lastProcessedId = (int) $this->getDataValue('last_processed_id');

        $stmt = $pdo->prepare(
            'SELECT * FROM changes_cache WHERE inversion > :last_id ORDER BY inversion ASC',
        );
        $stmt->execute([':last_id' => $lastProcessedId]);

        return $stmt->fetchAll();
    }

    /**
     * Update the last processed inversion ID for this source.
     *
     * @param int $inversion The last processed change version ID
     *
     * @return bool True on success
     */
    public function updateLastProcessed(int $inversion): bool
    {
        $this->setDataValue('last_processed_id', $inversion);

        return (bool) $this->updateToSQL(
            ['last_processed_id' => $inversion],
            ['id' => $this->getMyKey()],
        );
    }

    /**
     * Delete a processed record from the adapter's changes_cache.
     *
     * @param int $inversion The change version ID to remove
     *
     * @return bool True on success
     */
    public function wipeCacheRecord(int $inversion): bool
    {
        $pdo = $this->getConnection();
        $stmt = $pdo->prepare('DELETE FROM changes_cache WHERE inversion = :inversion');

        return $stmt->execute([':inversion' => $inversion]);
    }

    /**
     * Check if the adapter database is reachable.
     *
     * @return bool True if connection can be established
     */
    public function isReachable(): bool
    {
        try {
            $this->getConnection();

            return true;
        } catch (\PDOException $e) {
            $this->addStatusMessage(
                sprintf(_('Event source "%s" unreachable: %s'), $this->getRecordName(), $e->getMessage()),
                'warning',
            );

            return false;
        }
    }

    /**
     * Get all enabled event sources.
     *
     * @return array<int, array<string, mixed>> Array of enabled source records
     */
    public function getEnabledSources(): array
    {
        return $this->listingQuery()->where('enabled', true)->fetchAll();
    }

    /**
     * Close the adapter database connection on destruct.
     */
    public function __destruct()
    {
        $this->adapterConnection = null;
    }
}
