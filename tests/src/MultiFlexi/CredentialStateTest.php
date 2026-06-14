<?php

declare(strict_types=1);

namespace Test\MultiFlexi;

use MultiFlexi\CredentialCheckResult;
use MultiFlexi\CredentialState;
use PHPUnit\Framework\TestCase;

final class CredentialStateTest extends TestCase
{
    public function testAllCasesExist(): void
    {
        $this->assertSame('available',     CredentialState::Available->value);
        $this->assertSame('degraded',      CredentialState::Degraded->value);
        $this->assertSame('unavailable',   CredentialState::Unavailable->value);
        $this->assertSame('misconfigured', CredentialState::Misconfigured->value);
        $this->assertSame('unknown',       CredentialState::Unknown->value);
    }

    public function testIsSatisfiedReturnsTrueForAvailable(): void
    {
        $result = new CredentialCheckResult(CredentialState::Available, '', time());
        $this->assertTrue($result->isSatisfied());
    }

    public function testIsSatisfiedReturnsTrueForUnknown(): void
    {
        $result = new CredentialCheckResult(CredentialState::Unknown, '', time());
        $this->assertTrue($result->isSatisfied());
    }

    public function testIsSatisfiedReturnsFalseForDegraded(): void
    {
        $result = new CredentialCheckResult(CredentialState::Degraded, '', time());
        $this->assertFalse($result->isSatisfied());
    }

    public function testIsSatisfiedReturnsFalseForUnavailable(): void
    {
        $result = new CredentialCheckResult(CredentialState::Unavailable, '', time());
        $this->assertFalse($result->isSatisfied());
    }

    public function testIsSatisfiedReturnsFalseForMisconfigured(): void
    {
        $result = new CredentialCheckResult(CredentialState::Misconfigured, '', time());
        $this->assertFalse($result->isSatisfied());
    }
}
