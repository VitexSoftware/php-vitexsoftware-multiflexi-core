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
     * @param null|int $identifier Record ID
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
     * Build environment variable overrides from source data using env_mapping.
     *
     * Accepts any associative array as $source — a changes_cache record, a
     * decoded produces JSON, or any other flat/nested map. Selector syntax:
     *   - plain key or dot-path / JSONPath  → extract scalar
     *   - "@file:<producesName>"            → materialise to temp file, value = path
     *
     * @param array<string, mixed> $source       Source data (changes_cache row OR job produces payload)
     * @param array<string, mixed> $producedFiles Map of producesName => content to materialise for @file: selectors
     *
     * @return array<string, string> Key-value pairs of environment variables
     */
    public function buildEnvOverrides(array $source, array $producedFiles = []): array
    {
        $envOverrides = [];
        $mapping = $this->getEnvMapping();

        foreach ($mapping as $envKey => $selector) {
            $resolved = $this->resolveSelector($selector, $source, $producedFiles);

            if ($resolved !== null) {
                $envOverrides[$envKey] = $resolved;
            }
        }

        // Provide standard event metadata when the source looks like a changes_cache record
        if (!isset($envOverrides['EVENT_INVERSION'])) {
            $envOverrides['EVENT_INVERSION'] = (string) ($source['inversion'] ?? '');
        }

        if (!isset($envOverrides['EVENT_EVIDENCE'])) {
            $envOverrides['EVENT_EVIDENCE'] = (string) ($source['evidence'] ?? '');
        }

        if (!isset($envOverrides['EVENT_OPERATION'])) {
            $envOverrides['EVENT_OPERATION'] = (string) ($source['operation'] ?? '');
        }

        if (!isset($envOverrides['EVENT_RECORD_ID'])) {
            $envOverrides['EVENT_RECORD_ID'] = (string) ($source['recordid'] ?? '');
        }

        return $envOverrides;
    }

    /**
     * Resolve a single selector against source data.
     *
     * Selector forms:
     *   "@file:<producesName>"  — write $producedFiles[<producesName>] to a temp
     *                             file and return the file PATH as the value.
     *   "$.foo.bar" or "foo.bar" — dot-path into $source; supports nested arrays.
     *
     * @param array<string, mixed> $source
     * @param array<string, mixed> $producedFiles producesName => content (string or array)
     */
    private function resolveSelector(string $selector, array $source, array $producedFiles = []): ?string
    {
        // @file:<producesName> — materialise produced content to a temp file
        if (str_starts_with($selector, '@file:')) {
            $producesName = substr($selector, 6);

            if (!\array_key_exists($producesName, $producedFiles)) {
                return null;
            }

            $content = $producedFiles[$producesName];

            if (\is_array($content)) {
                $content = json_encode($content);
            }

            $tmpPath = tempnam(sys_get_temp_dir(), 'mf_chain_');

            if ($tmpPath === false) {
                return null;
            }

            file_put_contents($tmpPath, (string) $content);

            return $tmpPath;
        }

        // Dot-path / JSONPath — strip leading "$." for JSONPath compatibility
        $path = ltrim($selector, '$.');

        // Split on dots to walk nested arrays
        $segments = explode('.', $path);
        $current = $source;

        foreach ($segments as $segment) {
            if (!\is_array($current) || !\array_key_exists($segment, $current)) {
                return null;
            }

            $current = $current[$segment];
        }

        return \is_scalar($current) ? (string) $current : null;
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

    /**
     * Get all enabled rules that fire when a job belonging to $runtemplateId completes.
     *
     * @param int $runtemplateId Source RunTemplate ID
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getRulesForRunTemplate(int $runtemplateId): array
    {
        $instance = new self();

        return $instance->listingQuery()
            ->where('runtemplate_source_id', $runtemplateId)
            ->where('enabled', true)
            ->orderBy('priority DESC')
            ->fetchAll();
    }
}
