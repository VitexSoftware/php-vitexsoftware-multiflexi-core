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

namespace MultiFlexi\Flow;

use MultiFlexi\DBEngine;

/**
 * Persist and query Node-RED Deploy graphs as immutable flow versions.
 *
 * @author Vítězslav Dvořák <info@vitexsoftware.cz>
 */
class FlowRepository extends DBEngine
{
    public function __construct($identifier = null, $options = [])
    {
        $this->myTable = 'flow';
        $this->keyColumn = 'id';
        parent::__construct($identifier, $options);
    }

    /**
     * Upsert a flow from a Node-RED Deploy payload.
     *
     * Creates a new immutable flow_version when the checksum changes.
     * Existing in-flight flow_run rows keep their pinned version.
     *
     * Expected body:
     *   name, nodered_tab_id, company_id?, enabled?,
     *   nodes: [{id,type,name?,x?,y?,config?}],
     *   wires: [{from,from_port?,to,to_port?}],
     *   raw_json?
     *
     * @param array<string, mixed> $payload
     *
     * @throws \InvalidArgumentException on validation failure
     *
     * @return array<string, mixed> Flow + version summary
     */
    public function syncFromDeploy(array $payload): array
    {
        $tabId = trim((string) ($payload['nodered_tab_id'] ?? ''));
        $name = trim((string) ($payload['name'] ?? ''));

        if ($tabId === '') {
            throw new \InvalidArgumentException('nodered_tab_id is required');
        }

        if ($name === '') {
            $name = 'Flow '.$tabId;
        }

        $nodesIn = \is_array($payload['nodes'] ?? null) ? $payload['nodes'] : [];
        $wiresIn = \is_array($payload['wires'] ?? null) ? $payload['wires'] : [];

        $errors = FlowExecutableCatalog::validateNodes($nodesIn);

        if ($errors !== []) {
            throw new \InvalidArgumentException(implode('; ', $errors));
        }

        $nodes = [];

        foreach ($nodesIn as $node) {
            $type = (string) ($node['type'] ?? '');

            if (FlowExecutableCatalog::isIgnorable($type)) {
                continue;
            }

            $config = $node['config'] ?? $node;

            if (\is_array($config)) {
                unset($config['id'], $config['type'], $config['name'], $config['x'], $config['y'], $config['z'], $config['wires']);
            }

            $nodes[] = [
                'nr_node_id' => (string) $node['id'],
                'type' => $type,
                'name' => isset($node['name']) ? (string) $node['name'] : null,
                'config' => \is_array($config) ? $config : [],
                'x' => isset($node['x']) ? (int) $node['x'] : null,
                'y' => isset($node['y']) ? (int) $node['y'] : null,
            ];
        }

        $nodeIds = array_column($nodes, 'nr_node_id');
        $wires = [];

        foreach ($wiresIn as $wire) {
            $from = (string) ($wire['from'] ?? $wire['from_nr_node_id'] ?? '');
            $to = (string) ($wire['to'] ?? $wire['to_nr_node_id'] ?? '');

            if ($from === '' || $to === '') {
                continue;
            }

            if (!\in_array($from, $nodeIds, true) || !\in_array($to, $nodeIds, true)) {
                continue; // drop wires to ignorable/stripped nodes
            }

            $wires[] = [
                'from_nr_node_id' => $from,
                'from_port' => (int) ($wire['from_port'] ?? 0),
                'to_nr_node_id' => $to,
                'to_port' => (int) ($wire['to_port'] ?? 0),
            ];
        }

        $checksum = hash('sha256', (string) json_encode(['nodes' => $nodes, 'wires' => $wires]));

        $flow = $this->findByNoderedTabId($tabId);

        if ($flow === null) {
            $flowEngine = new Flow();
            $flowEngine->setDataValue('name', $name);
            $flowEngine->setDataValue('nodered_tab_id', $tabId);
            $flowEngine->setDataValue('company_id', $payload['company_id'] ?? null);
            $flowEngine->setDataValue('enabled', array_key_exists('enabled', $payload) ? (bool) $payload['enabled'] : true);

            if (!$flowEngine->dbsync()) {
                throw new \RuntimeException('Failed to create flow');
            }

            $flowId = (int) $flowEngine->getMyKey();
        } else {
            $flowId = (int) $flow['id'];
            $flowEngine = new Flow($flowId);
            $flowEngine->setDataValue('name', $name);

            if (\array_key_exists('company_id', $payload)) {
                $flowEngine->setDataValue('company_id', $payload['company_id']);
            }

            if (\array_key_exists('enabled', $payload)) {
                $flowEngine->setDataValue('enabled', (bool) $payload['enabled']);
            }

            $flowEngine->dbsync();
        }

        $currentVersionId = isset($flow['current_version_id']) ? (int) $flow['current_version_id'] : 0;

        if ($currentVersionId > 0) {
            $current = new FlowVersion($currentVersionId);

            if ($current->getDataValue('checksum') === $checksum) {
                return $this->exportFlow($flowId);
            }
        }

        $nextVersion = $this->nextVersionNumber($flowId);
        $versionEngine = new FlowVersion();
        $versionEngine->setDataValue('flow_id', $flowId);
        $versionEngine->setDataValue('version', $nextVersion);
        $versionEngine->setDataValue('checksum', $checksum);
        $versionEngine->setDataValue(
            'raw_json',
            isset($payload['raw_json']) ? (string) json_encode($payload['raw_json']) : null,
        );
        $versionEngine->setDataValue('interpreter_capability', FlowExecutableCatalog::INTERPRETER_CAPABILITY);

        if (!$versionEngine->dbsync()) {
            throw new \RuntimeException('Failed to create flow_version');
        }

        $versionId = (int) $versionEngine->getMyKey();
        $pdo = $this->getPdo();

        $nodeStmt = $pdo->prepare(
            'INSERT INTO flow_node (flow_version_id, nr_node_id, type, name, config, x, y) VALUES (?, ?, ?, ?, ?, ?, ?)',
        );

        foreach ($nodes as $node) {
            $nodeStmt->execute([
                $versionId,
                $node['nr_node_id'],
                $node['type'],
                $node['name'],
                json_encode($node['config'], \JSON_UNESCAPED_UNICODE),
                $node['x'],
                $node['y'],
            ]);
        }

        $wireStmt = $pdo->prepare(
            'INSERT INTO flow_wire (flow_version_id, from_nr_node_id, from_port, to_nr_node_id, to_port) VALUES (?, ?, ?, ?, ?)',
        );

        foreach ($wires as $wire) {
            $wireStmt->execute([
                $versionId,
                $wire['from_nr_node_id'],
                $wire['from_port'],
                $wire['to_nr_node_id'],
                $wire['to_port'],
            ]);
        }

        $flowEngine = new Flow($flowId);
        $flowEngine->setDataValue('current_version_id', $versionId);
        $flowEngine->dbsync();

        return $this->exportFlow($flowId);
    }

    /**
     * @return null|array<string, mixed>
     */
    public function findByNoderedTabId(string $tabId): ?array
    {
        $row = $this->listingQuery()->where('nodered_tab_id', $tabId)->fetch();

        return $row ?: null;
    }

    /**
     * Full flow export including current version nodes/wires.
     *
     * @return array<string, mixed>
     */
    public function exportFlow(int $flowId): array
    {
        $flow = new Flow($flowId);

        if (!$flow->getMyKey()) {
            throw new \RuntimeException('Flow not found');
        }

        $data = $flow->getData();
        $versionId = (int) ($data['current_version_id'] ?? 0);
        $data['enabled'] = (bool) ($data['enabled'] ?? false);
        $data['current_version'] = null;
        $data['nodes'] = [];
        $data['wires'] = [];

        if ($versionId > 0) {
            $version = new FlowVersion($versionId);
            $data['current_version'] = $version->getData();
            $data['nodes'] = $this->getNodes($versionId);
            $data['wires'] = $this->getWires($versionId);
        }

        return $data;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getNodes(int $flowVersionId): array
    {
        $rows = $this->getPdo()->prepare('SELECT * FROM flow_node WHERE flow_version_id = ? ORDER BY id ASC');
        $rows->execute([$flowVersionId]);
        $out = [];

        foreach ($rows->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $row['config'] = json_decode((string) ($row['config'] ?? '{}'), true) ?: [];
            $out[] = $row;
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getWires(int $flowVersionId): array
    {
        $stmt = $this->getPdo()->prepare('SELECT * FROM flow_wire WHERE flow_version_id = ? ORDER BY id ASC');
        $stmt->execute([$flowVersionId]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Enabled flows whose current version has a multiflexi-event trigger matching the change.
     *
     * @param array<string, mixed> $change
     *
     * @return list<array{flow: array, version_id: int, trigger_node: array}>
     */
    public function findTriggersForChange(array $change, ?int $eventSourceId = null): array
    {
        $pdo = $this->getPdo();
        $sql = 'SELECT f.*, n.id AS trigger_node_pk, n.nr_node_id, n.type AS trigger_type, n.config AS trigger_config, n.name AS trigger_name,
                       fv.id AS version_id, fv.interpreter_capability
                FROM flow f
                INNER JOIN flow_version fv ON fv.id = f.current_version_id
                INNER JOIN flow_node n ON n.flow_version_id = fv.id AND n.type = ?
                WHERE f.enabled = 1';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([FlowExecutableCatalog::TRIGGER_TYPES[0]]);
        $matches = [];

        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $config = json_decode((string) ($row['trigger_config'] ?? '{}'), true) ?: [];

            if ($eventSourceId !== null && isset($config['event_source_id']) && (int) $config['event_source_id'] !== $eventSourceId) {
                continue;
            }

            if (!$this->triggerMatches($config, $change)) {
                continue;
            }

            if (!$this->capabilityOk((string) ($row['interpreter_capability'] ?? '0'))) {
                continue;
            }

            $matches[] = [
                'flow' => [
                    'id' => (int) $row['id'],
                    'name' => $row['name'],
                    'company_id' => $row['company_id'],
                    'nodered_tab_id' => $row['nodered_tab_id'],
                ],
                'version_id' => (int) $row['version_id'],
                'trigger_node' => [
                    'nr_node_id' => $row['nr_node_id'],
                    'type' => $row['trigger_type'],
                    'name' => $row['trigger_name'],
                    'config' => $config,
                ],
            ];
        }

        return $matches;
    }

    /**
     * @param array<string, mixed> $config
     * @param array<string, mixed> $change
     */
    public function triggerMatches(array $config, array $change): bool
    {
        $evidence = $config['evidence'] ?? $config['topic'] ?? null;
        $operation = $config['operation'] ?? 'any';
        $changeEvidence = (string) ($change['evidence'] ?? '');
        $changeOperation = (string) ($change['operation'] ?? '');

        if ($evidence !== null && $evidence !== '' && (string) $evidence !== $changeEvidence) {
            return false;
        }

        if ($operation !== 'any' && (string) $operation !== $changeOperation) {
            return false;
        }

        return true;
    }

    private function capabilityOk(string $required): bool
    {
        return version_compare(FlowExecutableCatalog::INTERPRETER_CAPABILITY, $required, '>=');
    }

    private function nextVersionNumber(int $flowId): int
    {
        $stmt = $this->getPdo()->prepare('SELECT MAX(version) AS max_v FROM flow_version WHERE flow_id = ?');
        $stmt->execute([$flowId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return ((int) ($row['max_v'] ?? 0)) + 1;
    }

    /**
     * Cancel a running flow (skip pending steps). Waiting jobs are left to finish.
     */
    public function cancelRun(int $runId): bool
    {
        $run = new FlowRun($runId);

        if (!$run->getMyKey()) {
            return false;
        }

        $run->setDataValue('status', FlowRun::STATUS_CANCELLING);
        $run->dbsync();

        $pdo = $this->getPdo();
        $pdo->prepare(
            "UPDATE flow_run_step SET status = ?, error = 'cancelled' WHERE flow_run_id = ? AND status IN ('pending','ready','waiting_delay')",
        )->execute([FlowRunStep::STATUS_SKIPPED, $runId]);

        $run->setDataValue('status', FlowRun::STATUS_CANCELLED);
        $run->setDataValue('finished', date('Y-m-d H:i:s'));
        $run->dbsync();

        return true;
    }
}
