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

namespace MultiFlexi\CredentialProtoType;

/**
 * Description of FioBank.
 *
 * author Vitex <info@vitexsoftware.cz>
 *
 * @no-named-arguments
 */
class FioBank extends \MultiFlexi\CredentialProtoType implements \MultiFlexi\credentialTypeInterface, \MultiFlexi\checkableCredentialInterface
{
    public static string $logo = 'Fio.svg';

    public function __construct()
    {
        parent::__construct();

        // Define internal configuration fields
        $accountNumberField = new \MultiFlexi\ConfigField('ACCOUNT_NUMBER', 'string', _('Fio Bank Account Number'), _('Number of the Fio Bank account'));
        $accountNumberField->setHint('123456789/2010')->setValue('');

        $tokenField = new \MultiFlexi\ConfigField('FIO_TOKEN', 'string', _('Fio Bank Token'), _('Token for accessing the Fio Bank API'));
        $tokenField->setHint(_('AXWxJN18IqwbY....xccP2eyxvWDFLe2'))->setRequired(true)->setValue('');

        $tokenNameField = new \MultiFlexi\ConfigField('FIO_TOKEN_NAME', 'string', _('Fio Token Name'), _('Name of the token used for identification'));
        $tokenNameField->setHint('default-token')->setValue('');

        $this->configFieldsInternal->addField($accountNumberField);
        $this->configFieldsInternal->addField($tokenField);
        $this->configFieldsInternal->addField($tokenNameField);
    }

    #[\Override]
    public function uuid(): string
    {
        return 'f79aaa38-2eaf-453a-beee-3a2afa1221d5';
    }

    public function load(int $credTypeId)
    {
        $loaded = parent::load($credTypeId);

        // Load provided configuration fields
        foreach ($this->configFieldsInternal->getFields() as $field) {
            $this->configFieldsProvided->addField($field);
        }

        return $loaded;
    }

    #[\Override]
    public function prepareConfigForm(): void
    {
        // Implement the configuration form logic if needed
    }

    public function name(): string
    {
        return _('Fio Bank');
    }

    public function description(): string
    {
        return _('Fio Bank credential type for integration with Fio Bank API');
    }

    #[\Override]
    public function logo(): string
    {
        return self::$logo;
    }

    #[\Override]
    public function checkAvailability(): \MultiFlexi\CredentialCheckResult
    {
        $token = (string) ($this->configFieldsInternal->getFieldByCode('FIO_TOKEN')?->getValue() ?? '');

        if ($token === '') {
            return new \MultiFlexi\CredentialCheckResult(
                \MultiFlexi\CredentialState::Misconfigured,
                _('Fio API token is not set'),
                time(),
            );
        }

        // Host reachability only — no token-scoped endpoint (30 s rate limit + cursor side effect).
        $ch = curl_init('https://fioapi.fio.cz/');
        curl_setopt_array($ch, [\CURLOPT_NOBODY => true, \CURLOPT_CONNECTTIMEOUT => 5, \CURLOPT_TIMEOUT => 5]);
        curl_exec($ch);
        $errno = curl_errno($ch);

        return $errno === 0
            ? new \MultiFlexi\CredentialCheckResult(\MultiFlexi\CredentialState::Available, '', time(), 300)
            : new \MultiFlexi\CredentialCheckResult(
                \MultiFlexi\CredentialState::Unavailable,
                sprintf(_('Fio API host unreachable: %s'), curl_strerror($errno)),
                time(),
                60,
            );
    }
}
