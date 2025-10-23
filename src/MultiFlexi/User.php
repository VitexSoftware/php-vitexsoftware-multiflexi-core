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

use Ease\SQL\Orm;

/**
 * MultiFlexi - Instance Management Class.
 *
 * @author Vítězslav Dvořák <info@vitexsoftware.cz>
 * @copyright  2015-2025 Vitex Software
 */
class User extends \Ease\User
{
    use Orm;
    use \Ease\recordkey;
    public $useKeywords = [
        'login' => 'STRING',
        'firstname' => 'STRING',
        'lastname' => 'STRING',
        'email' => 'STRING',
    ];
    public $keywordsInfo = [
        'login' => [],
        'firstname' => [],
        'lastname' => [],
        'email' => [],
    ];
    public array $filter = [];

    /**
     * Tabulka uživatelů.
     */
    public string $myTable = 'user';

    /**
     * Sloupeček obsahující datum vložení záznamu do shopu.
     */
    public string $createColumn = 'DatCreate';

    /**
     * Slopecek obsahujici datum poslení modifikace záznamu do shopu.
     */
    public string $lastModifiedColumn = 'DatSave';

    /**
     * Engine Keyword.
     */
    public string $keyword = 'user';

    /**
     * MultiFlexi User.
     *
     * @param int|string $userID
     */
    public function __construct($userID = null)
    {
        $this->settingsColumn = 'settings';
        $this->nameColumn = 'login';
        parent::__construct($this->getDataValue('id'));

        if ($userID) {
            $this->setKeyColumn(is_numeric($userID) ? 'id' : 'login');
            $this->loadFromSQL($userID);
            $this->setKeyColumn('id');
        }
    }

    #[\Override]
    public function __sleep(): array
    {
        $this->pdo = null;
        $this->fluent = null;

        return array_merge(parent::__sleep(), ['myTable', 'keyColumn', 'createColumn', 'lastModifiedColumn']);
    }

    public function getNameColumn(): string
    {
        return 'login';
    }

    /**
     * Vrací odkaz na ikonu.
     */
    public function getIcon(): string
    {
        $Icon = $this->GetSettingValue('icon');

        if (null === $Icon) {
            return parent::getIcon();
        }

        return $Icon;
    }

    /**
     * Vrací ID aktuálního záznamu.
     *
     * @return int
     */
    public function getId()
    {
        return (int) $this->getMyKey();
    }

    /**
     * Give you user name.
     */
    public function getUserName(): string
    {
        $longname = trim($this->getDataValue('firstname').' '.$this->getDataValue('lastname'));

        if (\strlen($longname)) {
            return $longname;
        }

        return parent::getUserName();
    }

    public function getRecordName()
    {
        return $this->getUserName();
    }

    public function getEmail()
    {
        return $this->getDataValue('email');
    }

    /**
     * Pokusí se o přihlášení.
     * Try to Sign in.
     *
     * @param array $formData pole dat z přihlaš. formuláře např. $_REQUEST
     *
     * @return null|bool
     */
    public function tryToLogin(array $formData): bool
    {
        if (empty($formData) === true) {
            return false;
        }

        $login = addslashes($formData[$this->loginColumn]);
        $password = addslashes($formData[$this->passwordColumn]);
        
        // Initialize brute force protection if available
        $bruteForceProtection = null;
        if (class_exists('\\MultiFlexi\\Security\\BruteForceProtection') && isset($GLOBALS['bruteForceProtection'])) {
            $bruteForceProtection = $GLOBALS['bruteForceProtection'];
            
            // Check if login attempt is allowed
            $canAttempt = $bruteForceProtection->canAttemptLogin($login);
            if (!$canAttempt['allowed']) {
                $lockoutInfo = $canAttempt['lockout_info'];
                if ($canAttempt['reason'] === 'ip_locked') {
                    $this->addStatusMessage(
                        sprintf(
                            _('Too many failed login attempts from your IP address. Please try again in %d minutes.'),
                            ceil($lockoutInfo['remaining_time'] / 60)
                        ),
                        'error'
                    );
                } else {
                    $this->addStatusMessage(
                        sprintf(
                            _('Too many failed login attempts for this username. Please try again in %d minutes.'),
                            ceil($lockoutInfo['remaining_time'] / 60)
                        ),
                        'error'
                    );
                }
                
                // Enforce progressive delay
                $bruteForceProtection->enforceDelay($lockoutInfo['attempts']);
                return false;
            }
        }

        if (empty($login)) {
            $this->addStatusMessage(_('missing login'), 'event');

            return false;
        }

        if (empty($password)) {
            $this->addStatusMessage(_('missing password'), 'event');

            return false;
        }

        if ($this->loadFromSQL([$this->loginColumn => $login])) {
            $this->setObjectName();
            $currentHash = $this->getDataValue($this->passwordColumn);

            if (
                $this->passwordValidation(
                    $password,
                    $currentHash,
                )
            ) {
                if ($this->isAccountEnabled()) {
                    // Record successful login attempt
                    if ($bruteForceProtection) {
                        $bruteForceProtection->recordAttempt($login, true);
                        $bruteForceProtection->clearAttempts($login);
                    }
                    
                    // Log successful login
                    if (isset($GLOBALS['securityAuditLogger'])) {
                        $GLOBALS['securityAuditLogger']->logLoginSuccess($this->getUserID());
                    }
                    
                    // Automatically rehash password if needed (legacy MD5 or outdated bcrypt)
                    $this->rehashPasswordIfNeeded($password, $currentHash);
                    return $this->loginSuccess();
                }
                
                // Record failed login attempt (account disabled)
                if ($bruteForceProtection) {
                    $bruteForceProtection->recordAttempt($login, false);
                }

                $this->userID = null;

                return false;
            }
            
            // Record failed login attempt (invalid password)
            if ($bruteForceProtection) {
                $bruteForceProtection->recordAttempt($login, false);
            }
            
            // Log failed login attempt
            if (isset($GLOBALS['securityAuditLogger'])) {
                $GLOBALS['securityAuditLogger']->logLoginFailure($login, 'Invalid password');
            }

            $this->userID = null;

            if (!empty($this->getData())) {
                $this->addStatusMessage(_('invalid password'), 'event');
            }

            $this->dataReset();
            $result = false;
        } else {
            // Record failed login attempt (user not found)
            if ($bruteForceProtection) {
                $bruteForceProtection->recordAttempt($login, false);
            }
            
            // Log failed login attempt
            if (isset($GLOBALS['securityAuditLogger'])) {
                $GLOBALS['securityAuditLogger']->logLoginFailure($login, 'User not found');
            }
            
            $this->addStatusMessage(sprintf(
                _('user %s does not exist'),
                $login,
                'error',
            ));
            $result = false;
        }

        return $result;
    }

    /**
     * Ověření hesla.
     *
     * @param string $plainPassword     heslo v nešifrované podobě
     * @param string $encryptedPassword šifrovné heslo
     *
     * @return bool
     */
    public static function passwordValidation($plainPassword, $encryptedPassword)
    {
        if ($plainPassword && $encryptedPassword) {
            // Check if it's a new bcrypt hash
            if (str_starts_with($encryptedPassword, '$2y$') || str_starts_with($encryptedPassword, '$2a$') || str_starts_with($encryptedPassword, '$2b$')) {
                return password_verify($plainPassword, $encryptedPassword);
            }
            
            // Legacy MD5 hash support for backward compatibility
            $passwordStack = explode(':', $encryptedPassword);

            if (\count($passwordStack) !== 2) {
                return false;
            }

            if (md5($passwordStack[1].$plainPassword) === $passwordStack[0]) {
                return true;
            }
        }

        return false;
    }

    /**
     * Set logging.
     *
     * @return bool
     */
    public function loginSuccess()
    {
        $userId = $this->getUserID();

        LogToSQL::singleton()->setUser($userId);

        $_SESSION['user_id'] = $userId;
        $_SESSION['ws_token'] = bin2hex(random_bytes(16));

        return parent::loginSuccess();
    }

    /**
     * Perform User signoff.
     */
    public function logout(): bool
    {
        $this->dataReset();

        return parent::logout();
    }

    /**
     * Zašifruje heslo.
     *
     * @param string $plainTextPassword nešifrované heslo (plaintext)
     *
     * @return string Encrypted password
     */
    public static function encryptPassword($plainTextPassword)
    {
        // Use bcrypt with cost factor 12 for strong security
        return password_hash($plainTextPassword, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    /**
     * Změní uživateli uložené heslo.
     *
     * @param string $newPassword nové heslo
     *
     * @return string password hash
     */
    public function passwordChange($newPassword): bool
    {
        return $this->dbsync([$this->passwordColumn => $this->encryptPassword($newPassword), $this->getKeyColumn() => $this->getUserID()]);
    }
    
    /**
     * Check if password needs rehashing and rehash if necessary.
     *
     * @param string $plainPassword
     * @param string $currentHash
     * @return bool true if password was rehashed
     */
    public function rehashPasswordIfNeeded($plainPassword, $currentHash): bool
    {
        // If it's a legacy MD5 hash, rehash it with bcrypt
        if (!str_starts_with($currentHash, '$2y$') && !str_starts_with($currentHash, '$2a$') && !str_starts_with($currentHash, '$2b$')) {
            $newHash = $this->encryptPassword($plainPassword);
            return $this->dbsync([$this->passwordColumn => $newHash, $this->getKeyColumn() => $this->getUserID()]);
        }
        
        // Check if bcrypt hash needs rehashing (e.g., cost factor changed)
        if (password_needs_rehash($currentHash, PASSWORD_BCRYPT, ['cost' => 12])) {
            $newHash = $this->encryptPassword($plainPassword);
            return $this->dbsync([$this->passwordColumn => $newHash, $this->getKeyColumn() => $this->getUserID()]);
        }
        
        return false;
    }

    /**
     * Common instance of User class.
     *
     * @param null|mixed $user
     *
     * @return User
     */
    public static function singleton($user = null)
    {
        if (!isset(self::$instance)) {
            self::$instance = null === $user ? new self() : $user;
        }

        return self::$instance;
    }
}
