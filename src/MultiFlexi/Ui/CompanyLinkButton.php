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

namespace MultiFlexi\Ui;

use Ease\Html\ATag;

/**
 * Class CompanyLinkButton
 * Represents a link button for a company.
 */
class CompanyLinkButton extends ATag
{
    /**
     * CompanyLinkButton constructor.
     */
    public function __construct(object $company, array $properties = [])
    {
        $href = 'company.php?id='.$company->getMyKey();
        $label = $company->getDataValue('name');
        parent::__construct($href, $label, $properties);
    }
}
