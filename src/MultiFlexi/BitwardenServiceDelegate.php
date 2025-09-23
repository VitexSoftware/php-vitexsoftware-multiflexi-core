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
 * Description of BitwardenServiceDelegate.
 *
 * @author vitex
 */
class BitwardenServiceDelegate implements \Jalismrs\Bitwarden\BitwardenServiceDelegate
{
    private string $email;
    private string $password;

    /**
     * Initialize the delegate with Bitwarden user credentials.
     *
     * @param string $email The user's Bitwarden account email address.
     * @param string $password The user's Bitwarden account password.
     */
    public function __construct(string $email, string $password)
    {
        $this->email = $email;
        $this->password = $password;
    }

    /**
     * Return the Bitwarden organization ID used for operations, if any.
     *
     * Returns null when no organization is configured for this delegate.
     *
     * @return string|null The organization ID, or null if not set.
     */
    #[\Override]
    public function getOrganizationId(): ?string
    {
        return null;
    }

    /**
     * Returns the stored Bitwarden user email.
     *
     * @return string The email address provided to the service delegate.
     */
    #[\Override]
    public function getUserEmail(): string
    {
        return $this->email;
    }

    /**
     * Returns the stored user password for Bitwarden authentication.
     *
     * @return string The user's password.
     */
    #[\Override]
    public function getUserPassword(): string
    {
        return $this->password;
    }

    /**
     * Returns the restored session token, if available.
     *
     * This implementation does not persist sessions and always indicates that no session is available by returning an empty string.
     *
     * @return string|null The restored session token, or an empty string/null when no session is available.
     */
    #[\Override]
    public function restoreSession(): ?string
    {
        return '';
    }

    #[\Override]
    public function storeSession(string $session): void
    {
    }
}
