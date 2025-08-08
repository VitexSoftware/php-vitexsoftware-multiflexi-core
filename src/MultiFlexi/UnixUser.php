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
 * Description of UnixUser.
 *
 * @author Vitex <info@vitexsoftware.cz>
 */
class UnixUser extends \MultiFlexi\User
{
    public function __construct($userID = null)
    {
        /**
         * Attempt to log in using the current Unix username.
         * If the user does not exist in the 'user' table, create a new one.
         */
        /** @var string $unixUsername */
        $unixUsername = $userID ?? get_current_user();

        parent::__construct($unixUsername);

        // $this->loadFromSQL(['username' => self::$instance]);

        if (!$this->getMyKey()) {
            // User does not exist, create a new one unless DB is dummy
            $userData = [
                'login' => $unixUsername,
                'email' => $unixUsername.'@'.gethostname(),
                'password' => '',
                'enabled' => 0,
            ];

            if ($this->insertToSQL($userData)) {
                $this->addStatusMessage(sprintf(_('New unix user %s created'), $unixUsername));
            } else {
                throw new \Exception(_('Failed to create new user for current Unix username.'));
            }
        }
    }
}
