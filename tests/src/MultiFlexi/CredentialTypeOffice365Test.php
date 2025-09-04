<?php

declare(strict_types=1);

namespace Test\MultiFlexi;

use MultiFlexi\CredentialType\Office365;
use MultiFlexi\CredentialType\Common as CredentialCommon;
use PHPUnit\Framework\TestCase;

final class CredentialTypeOffice365Test extends TestCase
{
    public function testExtendsCredentialCommon(): void
    {
        $this->assertTrue(is_a(Office365::class, CredentialCommon::class, true));
    }
}
