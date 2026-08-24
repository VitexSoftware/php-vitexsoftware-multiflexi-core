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

    /**
     * Assign a single role (by id) to a user, additively — unlike
     * setUserRoles(), this never unassigns the user's other roles.
     */
    public function assignRoleToUser(int $userId, int $roleId, ?int $assignedBy = null, ?string $expiresAt = null): bool
    {
        return $this->getPdo()->prepare(
            'INSERT INTO rbac_user_roles (user_id, role_id, assigned_by, expires_at) VALUES (?, ?, ?, ?) '
            .'ON DUPLICATE KEY UPDATE assigned_by = VALUES(assigned_by), assigned_at = CURRENT_TIMESTAMP, expires_at = VALUES(expires_at)',
        )->execute([$userId, $roleId, $assignedBy, $expiresAt]);
    }

    // -----------------------------------------------------------------------
    // Permissions (rbac_permissions / rbac_role_permissions)
    //
    // Ported from multiflexi-web5's MultiFlexi\Security\RoleBasedAccessControl,
    // which had its own copy of this schema/logic. Session-based "current
    // user" resolution, audit logging, and result caching stay in web5 (they
    // depend on $_SESSION / $GLOBALS, which don't belong in a portable
    // library); this class covers the DB-backed read/write primitives only.
    // -----------------------------------------------------------------------

    /**
     * True when $userId holds a role granting $permissionName (not yet expired).
     */
    public function userHasPermission(int $userId, string $permissionName): bool
    {
        $stmt = $this->getPdo()->prepare(<<<'EOD'
SELECT 1
             FROM rbac_user_roles ur
             JOIN rbac_role_permissions rp ON ur.role_id = rp.role_id
             JOIN rbac_permissions p ON rp.permission_id = p.id
             WHERE ur.user_id = ?
               AND p.name = ?
               AND (ur.expires_at IS NULL OR ur.expires_at > CURRENT_TIMESTAMP)
             LIMIT 1
EOD, );
        $stmt->execute([$userId, $permissionName]);

        return (bool) $stmt->fetchColumn();
    }

    /**
     * Distinct permissions (name, description, resource, action) granted to
     * $userId via any of their roles.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getUserPermissions(int $userId): array
    {
        $stmt = $this->getPdo()->prepare(<<<'EOD'
SELECT DISTINCT p.name, p.description, p.resource, p.action
             FROM rbac_permissions p
             JOIN rbac_role_permissions rp ON p.id = rp.permission_id
             JOIN rbac_user_roles ur ON rp.role_id = ur.role_id
             WHERE ur.user_id = ?
               AND (ur.expires_at IS NULL OR ur.expires_at > CURRENT_TIMESTAMP)
             ORDER BY p.resource, p.action, p.name
EOD, );
        $stmt->execute([$userId]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getAllRoles(bool $includeInactive = false): array
    {
        $sql = 'SELECT * FROM rbac_roles'.($includeInactive ? '' : ' WHERE is_active = 1').' ORDER BY name';

        return $this->getPdo()->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getAllPermissions(): array
    {
        return $this->getPdo()->query('SELECT * FROM rbac_permissions ORDER BY resource, action, name')->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getRolePermissions(int $roleId): array
    {
        $stmt = $this->getPdo()->prepare(<<<'EOD'
SELECT p.*
             FROM rbac_permissions p
             JOIN rbac_role_permissions rp ON p.id = rp.permission_id
             WHERE rp.role_id = ?
             ORDER BY p.resource, p.action, p.name
EOD, );
        $stmt->execute([$roleId]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * True when any (non-expired) user currently holds $roleName. Useful for
     * first-run detection (e.g. "does a super_admin exist yet").
     */
    public function isRoleAssigned(string $roleName): bool
    {
        $stmt = $this->getPdo()->prepare(<<<'EOD'
SELECT 1
             FROM rbac_user_roles ur
             JOIN rbac_roles r ON ur.role_id = r.id
             WHERE r.name = ?
               AND r.is_active = 1
               AND (ur.expires_at IS NULL OR ur.expires_at > CURRENT_TIMESTAMP)
             LIMIT 1
EOD, );
        $stmt->execute([$roleName]);

        return (bool) $stmt->fetchColumn();
    }

    /**
     * Remove one role assignment from a user.
     */
    public function removeRoleFromUser(int $userId, int $roleId): bool
    {
        return $this->getPdo()->prepare('DELETE FROM rbac_user_roles WHERE user_id = ? AND role_id = ?')
            ->execute([$userId, $roleId]);
    }

    /**
     * Create (or update, matched by name) a role.
     *
     * @return null|int the role id, or null on failure
     */
    public function createRole(string $name, string $displayName, ?string $description = null, bool $isSystem = false): ?int
    {
        $this->getPdo()->prepare(<<<'EOD'
INSERT INTO rbac_roles (name, display_name, description, is_system, is_active)
             VALUES (?, ?, ?, ?, 1)
             ON DUPLICATE KEY UPDATE display_name = VALUES(display_name), description = VALUES(description), updated_at = CURRENT_TIMESTAMP
EOD, )->execute([$name, $displayName, $description, $isSystem ? 1 : 0]);

        $stmt = $this->getPdo()->prepare('SELECT id FROM rbac_roles WHERE name = ? LIMIT 1');
        $stmt->execute([$name]);
        $id = $stmt->fetchColumn();

        return $id !== false ? (int) $id : null;
    }

    /**
     * Create (or update, matched by name) a permission.
     *
     * @return null|int the permission id, or null on failure
     */
    public function createPermission(string $name, ?string $description = null, ?string $resource = null, ?string $action = null, bool $isSystem = false): ?int
    {
        $this->getPdo()->prepare(<<<'EOD'
INSERT INTO rbac_permissions (name, description, resource, action, is_system)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE description = VALUES(description), resource = VALUES(resource), action = VALUES(action), updated_at = CURRENT_TIMESTAMP
EOD, )->execute([$name, $description, $resource, $action, $isSystem ? 1 : 0]);

        $stmt = $this->getPdo()->prepare('SELECT id FROM rbac_permissions WHERE name = ? LIMIT 1');
        $stmt->execute([$name]);
        $id = $stmt->fetchColumn();

        return $id !== false ? (int) $id : null;
    }

    /**
     * Grant $permissionName to $roleId.
     */
    public function assignPermissionToRole(int $roleId, string $permissionName, ?int $grantedBy = null): bool
    {
        $stmt = $this->getPdo()->prepare('SELECT id FROM rbac_permissions WHERE name = ? LIMIT 1');
        $stmt->execute([$permissionName]);
        $permissionId = $stmt->fetchColumn();

        if ($permissionId === false) {
            return false;
        }

        return $this->getPdo()->prepare(
            'INSERT INTO rbac_role_permissions (role_id, permission_id, granted_by) VALUES (?, ?, ?) '
            .'ON DUPLICATE KEY UPDATE granted_at = CURRENT_TIMESTAMP',
        )->execute([$roleId, (int) $permissionId, $grantedBy]);
    }

    /**
     * Aggregate RBAC counters: total_roles, total_permissions,
     * users_with_roles, and the 10 most-assigned roles.
     *
     * @return array<string, mixed>
     */
    public function getStatistics(): array
    {
        $pdo = $this->getPdo();

        $popular = $pdo->query(<<<'EOD'
SELECT r.name, r.display_name, COUNT(ur.user_id) AS user_count
             FROM rbac_roles r
             LEFT JOIN rbac_user_roles ur ON r.id = ur.role_id
             WHERE r.is_active = 1
             GROUP BY r.id, r.name, r.display_name
             ORDER BY user_count DESC
             LIMIT 10
EOD, )->fetchAll(\PDO::FETCH_ASSOC);

        return [
            'total_roles' => (int) $pdo->query('SELECT COUNT(*) FROM rbac_roles WHERE is_active = 1')->fetchColumn(),
            'total_permissions' => (int) $pdo->query('SELECT COUNT(*) FROM rbac_permissions')->fetchColumn(),
            'users_with_roles' => (int) $pdo->query('SELECT COUNT(DISTINCT user_id) FROM rbac_user_roles')->fetchColumn(),
            'popular_roles' => $popular,
        ];
    }
}
