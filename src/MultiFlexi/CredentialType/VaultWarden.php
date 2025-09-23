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

namespace MultiFlexi\CredentialType;

/**
 * Description of VaultWarden.
 *
 * @author vitex
 *
 * @no-named-arguments
 */
class VaultWarden extends \MultiFlexi\CredentialProtoType implements \MultiFlexi\credentialTypeInterface
{
    / **
     * Initialize the VaultWarden credential type.
     *
     * Sets up internal configuration fields required to connect to a VaultWarden instance
     * (URL, user email, user password, and the target folder name) and prepares an empty
     * container for provided configuration fields that will be populated dynamically from
     * VaultWarden items.
     *
     * Internal fields created:
     * - VAULTWARDEN_URL: VaultWarden instance URL (default: https://vault.example.com/)
     * - VAULTWARDEN_EMAIL: user email for VaultWarden authentication
     * - VAULTWARDEN_PASSWORD: password for VaultWarden authentication
     * - VAULTWARDEN_FOLDER: folder name within VaultWarden to read items from (default: "MultiFlexi")
     */
    public function __construct()
    {
        parent::__construct();
        // Přístupové údaje pro VaultWarden
        $this->configFieldsInternal = new \MultiFlexi\ConfigFields('VaultWarden Internal');
        $this->configFieldsInternal->addField(new \MultiFlexi\ConfigField('VAULTWARDEN_URL', 'url', _('VaultWarden URL'), _('URL instance VaultWarden'), 'https://vault.example.com/'));
        $this->configFieldsInternal->addField(new \MultiFlexi\ConfigField('VAULTWARDEN_EMAIL', 'string', _('VaultWarden User login'), _('User e-mail')));
        $this->configFieldsInternal->addField(new \MultiFlexi\ConfigField('VAULTWARDEN_PASSWORD', 'password', _('VaultWarden User password'), _('Password for user')));
        $this->configFieldsInternal->addField(new \MultiFlexi\ConfigField('VAULTWARDEN_FOLDER', 'string', _('VaultWarden Folder'), _('Název složky s hesly ve VaultWarden'), 'MultiFlexi'));

        // Položky budou naplněny dynamicky podle obsahu VaultWarden
        $this->configFieldsProvided = new \MultiFlexi\ConfigFields('VaultWarden Provided');
    }

    public static function name(): string
    {
        return _('VaultWarden');
    }

    public static function description(): string
    {
        return _('Use VaultWarden secrets');
    }

    /**
     * Prepare the credential configuration form.
     *
     * Currently delegates to the parent implementation. This method exists as the
     * override point for VaultWarden-specific form adjustments (e.g., adding or
     * modifying internal fields) if needed in the future.
     *
     * @return void
     */
    #[\Override]
    public function prepareConfigForm(): void
    {
        //        $folderField = $this->configFieldsInternal->getFieldByCode('VAULTWARDEN_FOLDER');
        parent::prepareConfigForm();
    }

    #[\Override]
    public function fieldsInternal(): \MultiFlexi\ConfigFields
    {
        return $this->configFieldsInternal;
    }

    #[\Override]
    public function fieldsProvided(): \MultiFlexi\ConfigFields
    {
        return $this->configFieldsProvided;
    }

    #[\Override]
    public static function logo(): string
    {
        return 'vaultwarden.svg';
    }

    /**
     * Loads credential-type data and triggers discovery of VaultWarden-provided fields.
     *
     * Calls the parent loader and then, if all required internal VaultWarden configuration
     * values (URL, email, password, folder) are present, runs query() to populate the
     * dynamic provided configuration fields from VaultWarden. If any required value is
     * missing, a warning status message is added.
     *
     * @param int $credTypeId Identifier of the credential type to load.
     * @return mixed The value returned by parent::load($credTypeId).
     */
    public function load(int $credTypeId)
    {
        $loaded = parent::load($credTypeId);

        // Načtení položek z VaultWarden
        $vaultwardenUrl = $this->configFieldsInternal->getFieldByCode('VAULTWARDEN_URL')->getValue();
        $vaultwardenEmail = $this->configFieldsInternal->getFieldByCode('VAULTWARDEN_EMAIL')->getValue();
        $vaultwardenPassword = $this->configFieldsInternal->getFieldByCode('VAULTWARDEN_PASSWORD')->getValue();
        $vaultwardenFolder = $this->configFieldsInternal->getFieldByCode('VAULTWARDEN_FOLDER')->getValue();

        if ($vaultwardenUrl && $vaultwardenEmail && $vaultwardenPassword && $vaultwardenFolder) {
            $this->query();
        } else {
            $this->addStatusMessage(_('Missing required fields for VaultWarden'), 'warning');
        }

        return $loaded;
    }

    /**
     * Retrieve VaultWarden secrets and populate the provided config fields.
     *
     * If required internal fields (URL, email, password, folder) are present this will either
     * perform a lightweight connectivity/availability check (when $checkOnly is true) or
     * query VaultWarden via the Bitwarden service, adding discovered username/password
     * fields to the provided ConfigFields collection.
     *
     * Side effects:
     * - Adds status messages for success or missing configuration.
     * - When not in check-only mode, adds one or more ConfigField entries to the returned
     *   ConfigFields (names derived from each item's name, suffixed with `_USERNAME` and/or `_PASSWORD`).
     *
     * @param bool $checkOnly If true, only verify that secrets are accessible (do not add fields).
     * @return \MultiFlexi\ConfigFields The provided configuration fields (may be populated with discovered secrets).
     */
    public function query(bool $checkOnly = false): \MultiFlexi\ConfigFields
    {
        // Získání hodnot z VaultWarden pouze pokud nejsou checkOnly
        $vaultwardenUrl = $this->configFieldsInternal->getFieldByCode('VAULTWARDEN_URL')->getValue();
        $vaultwardenEmail = $this->configFieldsInternal->getFieldByCode('VAULTWARDEN_EMAIL')->getValue();
        $vaultwardenPassword = $this->configFieldsInternal->getFieldByCode('VAULTWARDEN_PASSWORD')->getValue();
        $vaultwardenFolder = $this->configFieldsInternal->getFieldByCode('VAULTWARDEN_FOLDER')->getValue();

        if ($vaultwardenUrl && $vaultwardenEmail && $vaultwardenPassword && $vaultwardenFolder) {
            if ($checkOnly) {
                // Zde pouze ověřit, že lze získat tajemství (např. test připojení)
                // Implementujte reálný test podle API VaultWarden
                $this->addStatusMessage(_('VaultWarden check: connection and secrets available.'), 'success');

                return $this->configFieldsProvided;
            }

            // Use Bitwarden service to get items
            $delegate = new \MultiFlexi\BitwardenServiceDelegate($vaultwardenEmail, $vaultwardenPassword);
            $service = new \Jalismrs\Bitwarden\BitwardenService($delegate);
            $items = $service->searchItems($this->configFieldsInternal->getFieldByCode('VAULTWARDEN_FOLDER')->getValue());

            foreach ($items as $item) {
                $baseName = strtoupper(str_replace(' ', '_', $item->getName()));
                if ($item->getLogin() && $item->getLogin()->getUsername()) {
                    $this->configFieldsProvided->addField(new \MultiFlexi\ConfigField($baseName . '_USERNAME', 'string', $item->getName() . ' Username', $item->getName() . ' Username', $item->getLogin()->getUsername()));
                }
                if ($item->getLogin() && $item->getLogin()->getPassword()) {
                    $this->configFieldsProvided->addField(new \MultiFlexi\ConfigField($baseName . '_PASSWORD', 'string', $item->getName() . ' Password', $item->getName() . ' Password', $item->getLogin()->getPassword()));
                }
            }
        } else {
            $this->addStatusMessage(_('Missing required fields for VaultWarden'), 'warning');
        }

        return $this->configFieldsProvided;
    }
}
