<?php

declare(strict_types=1);

namespace Test\MultiFlexi;

use MultiFlexi\CredentialType\Common as CredentialCommon;
use PHPUnit\Framework\TestCase;

final class CredentialTypeCommonTest extends TestCase
{
    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(CredentialCommon::class));
    }
}
