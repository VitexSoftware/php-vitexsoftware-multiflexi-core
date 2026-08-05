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
 * Thrown when data-at-rest encryption cannot be performed or reversed
 * because the configured ENCRYPTION_MASTER_KEY cannot decrypt the stored
 * per-purpose encryption keys (e.g. after ENCRYPTION_MASTER_KEY was changed
 * or regenerated without re-encrypting the existing key material), or no
 * master key is configured at all.
 *
 * getMessage() is intentionally generic and safe to show to end users: it
 * never contains ciphertext, key material, key names/versions, or other
 * technical detail. The full, actionable diagnosis (what's wrong and how
 * to fix it) lives on the chained previous exception, meant for
 * error_log()/audit trail consumption by an administrator, not display.
 */
class EncryptionUnavailableException extends \RuntimeException
{
    public function __construct(\Throwable $previous)
    {
        parent::__construct(
            'Data encryption is currently unavailable. Please contact the administrator.',
            0,
            $previous,
        );
    }
}
