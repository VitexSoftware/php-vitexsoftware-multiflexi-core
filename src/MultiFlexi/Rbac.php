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
 * Reads RBAC role assignments (rbac_roles / rbac_user_roles).
 *
 * Single source of truth for "does this user hold role X" checks, which
 * were previously hand-rolled as raw SQL in multiple places (multiflexi-cli
 * `user-role:set`, multiflexi-server `UserRoleApi`) each time RBAC was
 * touched. Role assignment (write side) still lives in those callers for
 * now; this class covers the read/check side.
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
}
