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

use Ease\Html\ImgTag;

/**
 * Class ExecutorImage
 * Represents an image for an executor.
 */
class ExecutorImage extends ImgTag
{
    /**
     * ExecutorImage constructor.
     */
    public function __construct(string $src, array $properties = [])
    {
        parent::__construct($src, 'Executor Image', $properties);
    }
}
