<?php

declare(strict_types=1);

namespace Test\MultiFlexi;

use MultiFlexi\CredentialType\FioBank;
use MultiFlexi\CredentialType\Common as CredentialCommon;
use PHPUnit\Framework\TestCase;

final class CredentialTypeFioBankTest extends TestCase
{
    public function testExtendsCredentialCommon(): void
    {
        $this->assertTrue(is_a(FioBank::class, CredentialCommon::class, true));
    }
}
