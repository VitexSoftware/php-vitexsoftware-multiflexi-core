<?php

declare(strict_types=1);

namespace Test\MultiFlexi;

use MultiFlexi\CredentialProtoType;
use PHPUnit\Framework\TestCase;

final class CredentialProtoTypeTest extends TestCase
{
    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(CredentialProtoType::class));
    }
}
