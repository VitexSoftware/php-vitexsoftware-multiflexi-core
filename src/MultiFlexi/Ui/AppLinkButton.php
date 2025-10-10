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
 * Class AppLinkButton
 * Represents a link button for an application.
 */
class AppLinkButton extends ATag
{
    /**
     * AppLinkButton constructor.
     */
    public function __construct(object $app, array $properties = [])
    {
        $href = 'app.php?id='.$app->getMyKey();
        $label = $app->getDataValue('name');
        parent::__construct($href, $label, $properties);
    }
}
