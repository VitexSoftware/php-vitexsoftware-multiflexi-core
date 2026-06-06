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
            return null !== $this->updateToSQL(['role' => $role], ['id' => $existing['id']]);
        }

        return null !== $this->insertToSQL([
            'company_id' => $companyId,
            'user_id' => $userId,
            'role' => $role,
        ]);
    }

    public function removeUser(int $userId): bool
    {
        $companyId = $this->company ? (int) $this->company->getMyKey() : 0;

        if ($companyId <= 0 || $userId <= 0) {
            return false;
        }

        return null !== $this->deleteFromSQL([
            'company_id' => $companyId,
            'user_id' => $userId,
        ]);
    }

    public function getCompaniesForUser(int $userId)
    {
        return $this->listingQuery()->where('user_id', $userId)->orderBy('company_id');
    }
}
