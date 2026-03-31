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

namespace MultiFlexi;

/**
 * @author Vítězslav Dvořák <info@vitexsoftware.cz>
 */
interface credentialTypeInterface
{
    public function uuid(): string;

    public function name(): string;

    public function description(): string;

    public function logo(): string;

    public function prepareConfigForm(): void;

    public function fieldsProvided(): ConfigFields;

    public function fieldsInternal(): ConfigFields;

    public function save(): bool;

    public function query(): ConfigFields;

    // TODO: public function validate(): bool;
}
