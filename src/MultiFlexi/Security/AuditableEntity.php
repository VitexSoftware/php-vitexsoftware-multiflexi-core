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
 * Shared helpers for classes that record create/update/delete actions into
 * security_audit_log. Used by MultiFlexi\DBEngine (covers every model that
 * extends it — web UI, CLI, and REST API all go through the same ORM
 * methods) and by MultiFlexi\User (which uses \Ease\SQL\Orm directly instead
 * of DBEngine).
 *
 * Requires the using class to also provide the Ease\SQL\Orm trait (for
 * getPdo()/getKeyColumn()/getMyKey()).
 */
trait AuditableEntity
{
    /**
     * Best-effort resolution of the primary key affected by a write, used
     * only for audit logging — falls back to null (still logged, just
     * without an entity_id) when $data is a multi-row filter that doesn't
     * name the key column directly.
     *
     * @param null|array|int      $data
     * @param array<string,mixed> $conditons
     */
    private function resolveAuditEntityId($data, array $conditons = []): ?int
    {
        if (\is_int($data)) {
            return $data;
        }

        $keyColumn = $this->getKeyColumn();

        if (\is_array($data) && \array_key_exists($keyColumn, $data)) {
            return (int) $data[$keyColumn];
        }

        if (\array_key_exists($keyColumn, $conditons)) {
            return (int) $conditons[$keyColumn];
        }

        $myKey = $this->getMyKey();

        return $myKey ? (int) $myKey : null;
    }

    /**
     * Write one row to security_audit_log for a create/update/delete on this
     * entity. Never throws — a logging failure must not break the operation
     * it is auditing.
     *
     * @param array<string,mixed> $context extra data to store alongside the entry (e.g. delete filter)
     */
    private function auditAction(string $action, ?int $entityId, array $context = []): void
    {
        $entityType = \Ease\Functions::baseClassName($this);

        AuditLog::record(
            $this->getPdo(),
            \MultiFlexi\User::singleton()->getUserID() ?: null,
            $entityType,
            $entityId,
            $action,
            \sprintf('%s %s #%s', $entityType, $action, $entityId ?? '?'),
            $context,
        );
    }
}
