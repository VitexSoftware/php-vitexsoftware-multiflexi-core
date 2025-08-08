<?php

declare(strict_types=1);

/**
 * This file is part of the MultiFlexi package
 *
 * https://multiflexi.eu/
 *
 * (c) Vítězslav Dvořák <http://vitexsoftware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Test\MultiFlexi;

use MultiFlexi\UnixUser;

class UnixUserTest extends \PHPUnit\Framework\TestCase
{
    public function testConstructorWithUserId(): void
    {
        $user = new UnixUser(1);
        $this->assertInstanceOf(UnixUser::class, $user);
    }

    public function testConstructorWithDefaultUser(): void
    {
        $user = new UnixUser('phpunit');
        $this->assertInstanceOf(UnixUser::class, $user);
    }
}
