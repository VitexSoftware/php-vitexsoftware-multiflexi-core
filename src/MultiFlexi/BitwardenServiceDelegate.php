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
    private ?string $url;

    public function __construct(string $email, string $password, ?string $url = null)
    {
        $this->email = $email;
        $this->password = $password;
        $this->url = $url;
    }

    #[\Override]
    public function getOrganizationId(): ?string
    {
        return null;
    }
    
    /**
     * Get the URL for the Bitwarden server
     */
    public function getUrl(): ?string
    {
        return $this->url;
    }

    #[\Override]
    public function getUserEmail(): string
    {
        return $this->email;
    }

    #[\Override]
    public function getUserPassword(): string
    {
        return $this->password;
    }

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
