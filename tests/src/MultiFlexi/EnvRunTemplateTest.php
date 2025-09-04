<?php

declare(strict_types=1);

namespace Test\MultiFlexi;

use MultiFlexi\Env\RunTemplate;
use PHPUnit\Framework\TestCase;

final class EnvRunTemplateTest extends TestCase
{
    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(RunTemplate::class));
    }
}
