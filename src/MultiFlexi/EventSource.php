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
 * Several tenants (e.g. AbraFlexi companies) may share one adapter database.
 * A source with a `server_url` sees only the changes of that tenant.
 *
 * @author Vítězslav Dvořák <info@vitexsoftware.cz>
 */
class EventSource extends DBEngine
{
    /**
     * Name of the change buffer table inside the adapter database.
     */
    public const CACHE_TABLE = 'changes_cache';

    /**
     * Default maximum number of changes fetched per poll cycle.
     */
    public const DEFAULT_BATCH_LIMIT = 100;

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
     * @var null|\PDO Cached PDO connection to adapter database
     */
    private ?\PDO $adapterConnection = null;

    /**
     * EventSource constructor.
     *
     * @param null|int $identifier Record ID
     * @param array    $options    Additional options
     */
    public function __construct($identifier = null, $options = [])
    {
        $this->myTable = 'event_source';
        $this->keyColumn = 'id';
        parent::__construct($identifier, $options);
    }

    /**
     * Close the adapter database connection on destruct.
     */
    public function __destruct()
    {
        $this->adapterConnection = null;
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
            case 'mysql':
            default:
                $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $database);

                break;
        }

        $this->adapterConnection = new \PDO($dsn, $username, $password, [
            \PDO::ATTR_TIMEOUT => 5,
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);

        return $this->adapterConnection;
    }

    /**
     * Fetch unprocessed changes from the adapter's changes_cache table.
     *
     * Rows with an inversion greater than last_processed_id are returned in
     * ascending order, limited to one batch (MULTIFLEXI_EVENT_BATCH, default 100).
     * When the source has a `server_url`, only the changes of that tenant are
     * returned (changes_cache.source is changesapi.id, one row per server/company).
     *
     * If the adapter writes a `context` column containing a JSON object, its
     * keys are merged into the top level of each returned row so that
     * EventRule env_mapping selectors can reference them directly. This is
     * the only adapter-specific contract: an optional JSON blob; its content
     * is entirely the adapter's concern — MultiFlexi does not interpret it.
     *
     * @return array<int, array<string, mixed>> Array of change records
     */
    public function getUnprocessedChanges(): array
    {
        $pdo = $this->getConnection();
        $lastProcessedId = (int) $this->getDataValue('last_processed_id');
        $limit = max(1, (int) \Ease\Shared::cfg('MULTIFLEXI_EVENT_BATCH', self::DEFAULT_BATCH_LIMIT));
        $serverUrl = trim((string) ($this->getDataValue('server_url') ?? ''));

        $tenantFilter = $serverUrl !== ''
            ? ' AND source IN (SELECT id FROM changesapi WHERE serverurl = :serverurl)'
            : '';

        $stmt = $pdo->prepare(sprintf(
            'SELECT * FROM %s WHERE inversion > :last_id%s ORDER BY inversion ASC LIMIT %d',
            self::CACHE_TABLE,
            $tenantFilter,
            $limit,
        ));
        $stmt->bindValue(':last_id', $lastProcessedId, \PDO::PARAM_INT);

        if ($serverUrl !== '') {
            $stmt->bindValue(':serverurl', rtrim($serverUrl, '/'));
        }

        $stmt->execute();
        $rows = $stmt->fetchAll();

        return array_map(static function (array $row): array {
            $context = $row['context'] ?? null;
            unset($row['context']);

            if ($context !== null) {
                $decoded = json_decode((string) $context, true);

                if (\is_array($decoded)) {
                    $row = array_merge($decoded, $row);
                }
            }

            return $row;
        }, $rows);
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
     * The cache is shared by every tenant of the acceptor and the inversion is the
     * change version of ONE AbraFlexi company, so two tenants regularly hold the
     * same inversion. A record is therefore removed by its own row id; deleting by
     * inversion alone would also destroy another tenant's unprocessed change.
     *
     * @param int      $inversion The change version ID (used only when the row id is unknown)
     * @param null|int $rowId     Primary key (changes_cache.id) of the processed record
     *
     * @return bool True on success
     */
    public function wipeCacheRecord(int $inversion, ?int $rowId = null): bool
    {
        $pdo = $this->getConnection();

        if ($rowId !== null) {
            $stmt = $pdo->prepare(sprintf('DELETE FROM %s WHERE id = :id', self::CACHE_TABLE));
            $stmt->bindValue(':id', $rowId, \PDO::PARAM_INT);
        } else {
            $stmt = $pdo->prepare(sprintf('DELETE FROM %s WHERE inversion = :inversion', self::CACHE_TABLE));
            $stmt->bindValue(':inversion', $inversion, \PDO::PARAM_INT);
        }

        return $stmt->execute();
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
            $this->adapterConnection = null;
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
        return $this->listingQuery()->where('enabled', true)->fetchAll() ?: [];
    }
}
