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
 * Reads and assigns RBAC role assignments (rbac_roles / rbac_user_roles).
 *
 * Single source of truth for RBAC checks and role assignment, which were
 * previously hand-rolled as raw SQL in multiple places (multiflexi-cli
 * `user-role:set`, multiflexi-server `UserRoleApi`) every time RBAC was
 * touched. Role assignment uses `ON DUPLICATE KEY UPDATE`, which is
 * MySQL-specific — matching the behavior of the code this replaces.
 */
class Rbac extends DBEngine
{
    public function __construct()
    {
        parent::__construct();
        $this->setMyTable('rbac_user_roles');
    }

    /**
     * True when $userId currently holds at least one of the given roles
     * (role must be active and, if assigned with an expiry, not yet expired).
     *
     * @param array<string> $roleNames
     */
    public function userHasRole(int $userId, array $roleNames): bool
    {
        if ($userId <= 0 || empty($roleNames)) {
            return false;
        }

        $placeholders = implode(',', array_fill(0, \count($roleNames), '?'));
        $stmt = $this->getPdo()->prepare(<<<EOD
SELECT 1
             FROM rbac_user_roles ur
             JOIN rbac_roles r ON r.id = ur.role_id
             WHERE ur.user_id = ?
               AND r.is_active = 1
               AND r.name IN ({$placeholders})
               AND (ur.expires_at IS NULL OR ur.expires_at > CURRENT_TIMESTAMP)
             LIMIT 1
EOD, );
        $stmt->execute(array_merge([$userId], $roleNames));

        return (bool) $stmt->fetchColumn();
    }

    /**
     * Active, non-expired role names currently held by $userId.
     *
     * @return array<string>
     */
    public function getUserRoles(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        $stmt = $this->getPdo()->prepare(<<<'EOD'
SELECT r.name
             FROM rbac_user_roles ur
             JOIN rbac_roles r ON r.id = ur.role_id
             WHERE ur.user_id = ?
               AND r.is_active = 1
               AND (ur.expires_at IS NULL OR ur.expires_at > CURRENT_TIMESTAMP)
EOD, );
        $stmt->execute([$userId]);

        return array_column($stmt->fetchAll(\PDO::FETCH_ASSOC), 'name');
    }

    /**
     * Active role names mapped to their id, e.g. ['admin' => 2, 'viewer' => 5].
     *
     * @return array<string, int>
     */
    public function getAvailableRoles(): array
    {
        $stmt = $this->getPdo()->query('SELECT id, name FROM rbac_roles WHERE is_active = 1');
        $map = [];

        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $map[(string) $row['name']] = (int) $row['id'];
        }

        return $map;
    }

    /**
     * Full role detail rows (id, name, display_name, assigned_at, expires_at)
     * currently held by $userId.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getUserRoleDetails(int $userId): array
    {
        $stmt = $this->getPdo()->prepare(<<<'EOD'
SELECT r.id, r.name, r.display_name, ur.assigned_at, ur.expires_at
             FROM rbac_roles r
             JOIN rbac_user_roles ur ON ur.role_id = r.id
             WHERE ur.user_id = ? AND r.is_active = 1
             ORDER BY r.name
EOD, );
        $stmt->execute([$userId]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Assign $roleNames to $userId. When $replace is true (default), any
     * existing role not in $roleNames is unassigned; otherwise roles are
     * added on top of the user's current ones.
     *
     * @param array<string> $roleNames
     *
     * @throws \InvalidArgumentException when a role name is unknown/inactive
     *
     * @return array<int, array<string, mixed>> the user's role details after the update
     */
    public function setUserRoles(int $userId, array $roleNames, bool $replace = true, ?int $assignedBy = null): array
    {
        $available = $this->getAvailableRoles();
        $missing = array_values(array_diff($roleNames, array_keys($available)));

        if (!empty($missing)) {
            throw new \InvalidArgumentException('Unknown role(s): '.implode(', ', $missing));
        }

        $targetRoleIds = array_values(array_map(static fn (string $name): int => $available[$name], $roleNames));
        $pdo = $this->getPdo();

        $pdo->beginTransaction();

        try {
            if ($replace) {
                if (empty($targetRoleIds)) {
                    $pdo->prepare('DELETE FROM rbac_user_roles WHERE user_id = ?')->execute([$userId]);
                } else {
                    $placeholders = implode(',', array_fill(0, \count($targetRoleIds), '?'));
                    $pdo->prepare("DELETE FROM rbac_user_roles WHERE user_id = ? AND role_id NOT IN ({$placeholders})")
                        ->execute(array_merge([$userId], $targetRoleIds));
                }
            }

            foreach ($targetRoleIds as $roleId) {
                $pdo->prepare(
                    'INSERT INTO rbac_user_roles (user_id, role_id, assigned_by) VALUES (?, ?, ?) '
                    .'ON DUPLICATE KEY UPDATE assigned_by = VALUES(assigned_by), assigned_at = CURRENT_TIMESTAMP',
                )->execute([$userId, $roleId, $assignedBy]);
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();

            throw $e;
        }

        return $this->getUserRoleDetails($userId);
    }
}
