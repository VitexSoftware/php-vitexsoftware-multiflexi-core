<?php

declare(strict_types=1);

namespace Test\MultiFlexi;

use MultiFlexi\CredentialType\Csas;
use MultiFlexi\CredentialType\Common as CredentialCommon;
use PHPUnit\Framework\TestCase;

final class CredentialTypeCsasTest extends TestCase
{
    public function testExtendsCredentialCommon(): void
    {
        $this->assertTrue(is_a(Csas::class, CredentialCommon::class, true));
    }
}
