<?php

declare(strict_types=1);

namespace Test\MultiFlexi;

use MultiFlexi\checkableCredentialInterface;
use MultiFlexi\CredentialProtoType\FioBank;
use MultiFlexi\CredentialState;
use PHPUnit\Framework\TestCase;

final class CredentialProtoTypeFioBankCheckTest extends TestCase
{
    public function testImplementsCheckableInterface(): void
    {
        $this->assertInstanceOf(checkableCredentialInterface::class, new FioBank());
    }

    public function testCheckAvailabilityReturnsMisconfiguredWhenTokenEmpty(): void
    {
        $fioBank = new FioBank();
        $result  = $fioBank->checkAvailability();
        $this->assertSame(CredentialState::Misconfigured, $result->state);
    }
}
