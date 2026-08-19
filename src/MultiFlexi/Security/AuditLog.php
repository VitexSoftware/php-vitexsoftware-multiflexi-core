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
 * Writes generic entity create/update/delete audit rows into
 * security_audit_log. Used by MultiFlexi\DBEngine and MultiFlexi\User so
 * every model save/delete is recorded regardless of whether it happened via
 * the web UI, the CLI, or the REST API — they all funnel through the same
 * ORM methods.
 *
 * A logging failure must never break the operation being audited, so all
 * errors here are swallowed.
 */
final class AuditLog
{
    public static function record(
        \PDO $pdo,
        ?int $userId,
        string $entityType,
        ?int $entityId,
        string $action,
        string $description,
        array $additionalData = [],
        string $severity = 'low',
    ): void {
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO security_audit_log '.
                '(user_id, entity_type, entity_id, action, event_type, event_description, severity, additional_data) '.
                'VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            );
            $stmt->execute([
                $userId,
                $entityType,
                $entityId,
                $action,
                'entity_'.$action,
                $description,
                $severity,
                $additionalData ? json_encode($additionalData) : null,
            ]);
        } catch (\Throwable) {
            // Audit logging is best-effort: never let it break the primary operation.
        }
    }
}
