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
 * Read model over security_audit_log, including the generic entity
 * create/update/delete rows written by MultiFlexi\Security\AuditLog (see
 * MultiFlexi\Security\AuditableEntity) alongside the pre-existing
 * authentication/security events.
 */
class AuditLogEntry extends \MultiFlexi\DBEngine
{
    public string $myTable = 'security_audit_log';
    public ?string $createColumn = 'created_at';
    public ?string $keyword = 'auditlog';

    public function __construct($id = null)
    {
        $this->nameColumn = 'event_description';
        parent::__construct($id);
    }

    /**
     * @param array $columns
     *
     * @return array
     */
    public function columns($columns = [])
    {
        return parent::columns([
            ['name' => 'id', 'type' => 'text', 'label' => _('ID')],
            ['name' => 'entity_type', 'type' => 'text', 'label' => _('Entity')],
            ['name' => 'entity_id', 'type' => 'text', 'label' => _('#')],
            ['name' => 'action', 'type' => 'text', 'label' => _('Action')],
            ['name' => 'event_description', 'type' => 'text', 'label' => _('Description')],
            ['name' => 'severity', 'type' => 'text', 'label' => _('Severity')],
            ['name' => 'created_at', 'type' => 'datetime', 'label' => _('When')],
            ['name' => 'user_id', 'type' => 'selectize', 'label' => _('User'),
                'listingPage' => 'users.php',
                'detailPage' => 'user.php',
                'idColumn' => 'user',
                'valueColumn' => 'user.login',
                'engine' => '\MultiFlexi\User',
                'filterby' => 'name',
            ],
        ]);
    }
}
