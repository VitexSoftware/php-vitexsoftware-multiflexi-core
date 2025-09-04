<?php

declare(strict_types=1);

namespace Test\MultiFlexi;

use MultiFlexi\CredentialType\mServer;
use MultiFlexi\CredentialType\Common as CredentialCommon;
use PHPUnit\Framework\TestCase;

final class CredentialTypemServerTest extends TestCase
{
    public function testExtendsCredentialCommon(): void
    {
        $this->assertTrue(is_a(mServer::class, CredentialCommon::class, true));
    }
}
