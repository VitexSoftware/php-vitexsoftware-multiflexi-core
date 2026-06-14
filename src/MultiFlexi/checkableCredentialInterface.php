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
 * Optional marker interface for credential prototypes that implement a live
 * availability check. The base CredentialProtoType provides a no-op default,
 * so existing prototypes without a check are never blocked.
 */
interface checkableCredentialInterface
{
    public function checkAvailability(): CredentialCheckResult;
}
