<?php

declare(strict_types=1);

namespace Test\MultiFlexi;

use MultiFlexi\CredentialType\RaiffeisenBank;
use MultiFlexi\CredentialType\Common as CredentialCommon;
use PHPUnit\Framework\TestCase;

final class CredentialTypeRaiffeisenBankTest extends TestCase
{
    public function testExtendsCredentialCommon(): void
    {
        $this->assertTrue(is_a(RaiffeisenBank::class, CredentialCommon::class, true));
    }
}
