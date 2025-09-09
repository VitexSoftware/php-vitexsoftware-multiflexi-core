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

namespace Test\MultiFlexi;

use MultiFlexi\CredentialType\Common as CredentialCommon;
use MultiFlexi\CredentialType\SQLServer;
use PHPUnit\Framework\TestCase;

final class CredentialTypeSQLServerTest extends TestCase
{
    public function testExtendsCredentialCommon(): void
    {
        $this->assertTrue(is_a(SQLServer::class, CredentialCommon::class, true));
    }
}
