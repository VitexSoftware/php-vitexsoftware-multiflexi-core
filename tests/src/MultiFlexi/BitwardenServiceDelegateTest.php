<?php

declare(strict_types=1);

namespace Test\MultiFlexi;

use MultiFlexi\BitwardenServiceDelegate;
use PHPUnit\Framework\TestCase;

final class BitwardenServiceDelegateTest extends TestCase
{
    public function testClassExistsOrSkip(): void
    {
        if (!interface_exists('Jalismrs\\Bitwarden\\BitwardenServiceDelegate')) {
            $this->markTestSkipped('External dependency Jalismrs\\Bitwarden is not installed.');
            return;
        }
        $this->assertTrue(class_exists(BitwardenServiceDelegate::class));
    }
}
