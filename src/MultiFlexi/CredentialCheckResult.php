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
 * Structured result of a credential availability check.
 *
 * @param array<string,string> $details optional payload for the UI (server status, company, ...)
 */
final class CredentialCheckResult
{
    public function __construct(
        public readonly CredentialState $state,
        public readonly string $message = '',
        public readonly int $checkedAt = 0,
        public readonly int $ttl = 300,
        public readonly array $details = [],
    ) {
    }

    /**
     * Whether this result allows a Job to proceed.
     *
     * Unknown never blocks (backward compatibility with prototypes that have no check).
     * Degraded is intentionally NOT satisfied — busy ≠ available for scheduling.
     */
    public function isSatisfied(): bool
    {
        return $this->state === CredentialState::Available
            || $this->state === CredentialState::Unknown;
    }
}
