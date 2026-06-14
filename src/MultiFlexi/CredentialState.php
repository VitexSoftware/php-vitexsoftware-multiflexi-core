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

enum CredentialState: string
{
    case Available     = 'available';      // topic live → Job may run
    case Degraded      = 'degraded';       // reachable but impaired (e.g. server busy)
    case Unavailable   = 'unavailable';    // transient failure → defer / retry with backoff
    case Misconfigured = 'misconfigured';  // permanent → requires user action, no retry
    case Unknown       = 'unknown';        // no check implemented → do not block
}
