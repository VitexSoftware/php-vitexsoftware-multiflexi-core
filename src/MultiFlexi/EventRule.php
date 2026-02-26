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
 * EventRule maps an incoming change event to a MultiFlexi RunTemplate.
 *
 * Rules define which (evidence + operation) combination triggers which
 * RunTemplate, and how event data is transformed into job input
 * (environment variables).
 *
 * @author Vítězslav Dvořák <info@vitexsoftware.cz>
 */
class EventRule extends DBEngine
{
    /**
     * Operation constant: match any operation.
     */
    public const OPERATION_ANY = 'any';

    /**
     * Operation constant: match create operations only.
     */
    public const OPERATION_CREATE = 'create';

    /**
     * Operation constant: match update operations only.
     */
    public const OPERATION_UPDATE = 'update';

    /**
     * Operation constant: match delete operations only.
     */
    public const OPERATION_DELETE = 'delete';

    /**
     * @var string Name Column
     */
    public string $nameColumn = 'id';

    /**
     * @var string Create column name
     */
    public ?string $createColumn = 'created';

    /**
     * @var string Last modified column name
     */
    public ?string $lastModifiedColumn = 'modified';

    /**
     * EventRule constructor.
     *
     * @param int|null $identifier Record ID
     * @param array    $options    Additional options
     */
    public function __construct($identifier = null, $options = [])
    {
        $this->myTable = 'event_rule';
        $this->keyColumn = 'id';
        parent::__construct($identifier, $options);
    }

    /**
     * Check if this rule matches a given change record.
     *
     * @param array<string, mixed> $change Change record from changes_cache
     *
     * @return bool True if the rule matches the change
     */
    public function matches(array $change): bool
    {
        if (!(bool) $this->getDataValue('enabled')) {
            return false;
        }

        $ruleEvidence = $this->getDataValue('evidence');
        $ruleOperation = $this->getDataValue('operation');
        $changeEvidence = $change['evidence'] ?? '';
        $changeOperation = $change['operation'] ?? '';

        // Evidence must match (null means "any evidence")
        if ($ruleEvidence !== null && $ruleEvidence !== '' && $ruleEvidence !== $changeEvidence) {
            return false;
        }

        // Operation must match ("any" means match all operations)
        if ($ruleOperation !== self::OPERATION_ANY && $ruleOperation !== $changeOperation) {
            return false;
        }

        return true;
    }

    /**
     * Build environment variable overrides from change data using env_mapping.
     *
     * The env_mapping is a JSON object where keys are target env var names
     * and values are source field names from the change record.
     *
     * Example env_mapping: {"RECORD_ID": "recordid", "EVIDENCE": "evidence", "OPERATION": "operation"}
     *
     * @param array<string, mixed> $change Change record from changes_cache
     *
     * @return array<string, string> Key-value pairs of environment variables
     */
    public function buildEnvOverrides(array $change): array
    {
        $envOverrides = [];
        $mapping = $this->getEnvMapping();

        foreach ($mapping as $envKey => $sourceField) {
            if (\array_key_exists($sourceField, $change)) {
                $envOverrides[$envKey] = (string) $change[$sourceField];
            }
        }

        // Always provide standard event metadata
        if (!isset($envOverrides['EVENT_INVERSION'])) {
            $envOverrides['EVENT_INVERSION'] = (string) ($change['inversion'] ?? '');
        }

        if (!isset($envOverrides['EVENT_EVIDENCE'])) {
            $envOverrides['EVENT_EVIDENCE'] = (string) ($change['evidence'] ?? '');
        }

        if (!isset($envOverrides['EVENT_OPERATION'])) {
            $envOverrides['EVENT_OPERATION'] = (string) ($change['operation'] ?? '');
        }

        if (!isset($envOverrides['EVENT_RECORD_ID'])) {
            $envOverrides['EVENT_RECORD_ID'] = (string) ($change['recordid'] ?? '');
        }

        return $envOverrides;
    }

    /**
     * Parse the env_mapping JSON field.
     *
     * @return array<string, string> Parsed mapping or empty array
     */
    public function getEnvMapping(): array
    {
        $raw = $this->getDataValue('env_mapping');

        if (empty($raw)) {
            return [];
        }

        $decoded = json_decode($raw, true);

        return \is_array($decoded) ? $decoded : [];
    }

    /**
     * Get the RunTemplate ID this rule triggers.
     *
     * @return int RunTemplate ID
     */
    public function getRuntemplateId(): int
    {
        return (int) $this->getDataValue('runtemplate_id');
    }

    /**
     * Get all enabled rules for a given event source.
     *
     * @param int $eventSourceId Event source ID
     *
     * @return array<int, array<string, mixed>> Array of matching rule records
     */
    public function getRulesForSource(int $eventSourceId): array
    {
        return $this->listingQuery()
            ->where('event_source_id', $eventSourceId)
            ->where('enabled', true)
            ->orderBy('priority DESC')
            ->fetchAll();
    }
}
