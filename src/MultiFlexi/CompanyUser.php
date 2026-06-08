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
 * Description of CompanyUser.
 *
 * @author vitex
 */
class CompanyUser extends Engine
{
    public ?Company $company;

    /**
     * @param array $options
     */
    public function __construct(?Company $company = null, $options = [])
    {
        $this->myTable = 'company_user';
        parent::__construct(null, $options);
        $this->company = $company;
    }

    public function getAssigned()
    {
        return $this->listingQuery()->where('company_id', $this->company->getMyKey())->orderBy('user_id');
    }

    public function assignUser(int $userId, string $role = 'viewer'): bool
    {
        $companyId = $this->company ? (int) $this->company->getMyKey() : 0;

        if ($companyId <= 0 || $userId <= 0) {
            return false;
        }

        $existing = $this->listingQuery()->where(['company_id' => $companyId, 'user_id' => $userId])->fetch();

        if ($existing) {
            $success = null !== $this->updateToSQL(['role' => $role], ['id' => $existing['id']]);
        } else {
            $success = null !== $this->insertToSQL([
                'company_id' => $companyId,
                'user_id' => $userId,
                'role' => $role,
            ]);
        }

        if ($success) {
            $this->logAssignmentEvent('company_user_assigned', "User {$userId} assigned to company {$companyId} as {$role}", $userId, $companyId, $role);
        }

        return $success;
    }

    public function removeUser(int $userId): bool
    {
        $companyId = $this->company ? (int) $this->company->getMyKey() : 0;

        if ($companyId <= 0 || $userId <= 0) {
            return false;
        }

        $success = null !== $this->deleteFromSQL([
            'company_id' => $companyId,
            'user_id' => $userId,
        ]);

        if ($success) {
            $this->logAssignmentEvent('company_user_removed', "User {$userId} removed from company {$companyId}", $userId, $companyId);
        }

        return $success;
    }

    public function getCompaniesForUser(int $userId)
    {
        return $this->listingQuery()->where('user_id', $userId)->orderBy('company_id');
    }

    /**
     * Record a company-user assignment change in the security audit log.
     *
     * Uses the loosely-coupled $GLOBALS['securityAuditLogger'] so the core has no
     * hard dependency on the web application's logger. Never throws.
     *
     * @param string      $eventType   Audit event type
     * @param string      $description Human-readable description
     * @param int         $userId      Affected (assigned/removed) user ID
     * @param int         $companyId   Company ID
     * @param null|string $role        Role granted (assignment only)
     */
    private function logAssignmentEvent(string $eventType, string $description, int $userId, int $companyId, ?string $role = null): void
    {
        if (!isset($GLOBALS['securityAuditLogger'])) {
            return;
        }

        $actor = null;

        if (class_exists('\\Ease\\Shared') && method_exists('\\Ease\\Shared', 'user')) {
            $actorKey = \Ease\Shared::user()->getMyKey();
            $actor = $actorKey ? (int) $actorKey : null;
        }

        $data = ['user_id' => $userId, 'company_id' => $companyId, 'assigned_by' => $actor];

        if (null !== $role) {
            $data['role'] = $role;
        }

        try {
            $GLOBALS['securityAuditLogger']->logEvent($eventType, $description, 'medium', $actor, $data);
        } catch (\Throwable $e) {
            error_log('CompanyUser audit logging failed: '.$e->getMessage());
        }
    }
}
