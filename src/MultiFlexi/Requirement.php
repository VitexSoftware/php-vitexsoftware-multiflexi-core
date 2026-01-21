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
 * Description of Requirement.
 *
 * @author Vitex <info@vitexsoftware.cz>
 */
class Requirement
{
    /**
     * List of classes in \MultiFlexi\CredentialType\ name space.
     *
     * @return array<string, string>
     */
    public static function getCredentialProviders(): array
    {
        $credprototyper = new CredentialProtoType();
        return $credprototyper->getColumnsFromSQL(['name','code'], [], 'name', 'code');
    }

    /**
     * List of credential types.
     */
    public static function getCredentialTypes(Company $company): array
    {
        $credentialTypes = [];
        $credentialType = new CredentialType();

        foreach ($credentialType->listingQuery()->where('credential_type.company_id', $company->getMyKey()) as $credType) {
            $credentialTypes[$credType['class']][$credType['id']] = $credType;
        }

        return $credentialTypes;
    }

    /**
     * List of company credentials.
     *
     * @return type
     */
    public static function getCredentials(Company $company): array
    {
        $credentialsByType = [];
        $credentialType = new Credential();

        foreach ($credentialType->listingQuery()->select(['credential_type.*', 'credentials.id AS credential_id'])->leftJoin('credential_type ON credentials.credential_type_id = credential_type.id')->where('credential_type.company_id', $company->getMyKey()) as $credential) {
            if ($credential['credential_type_id']) {
                $credentialsByType[$credential['class']][$credential['credential_id']] = $credential;
            }
        }

        return $credentialsByType;
    }

    /**
     * Get PSR-4 directories for a namespace.
     *
     * @param string $namespace The namespace to find directories for
     *
     * @return array List of directory paths
     */
    private static function getPsr4DirsForNamespace(string $namespace): array
    {
        $dirs = [];

        // Find the composer autoloader
        $autoloaderFiles = array_filter(get_included_files(), static function ($file) {
            return str_contains($file, 'autoload.php');
        });

        if (empty($autoloaderFiles)) {
            return $dirs;
        }

        // Get the PSR-4 mappings
        $autoloaderFile = reset($autoloaderFiles);
        $psr4File = \dirname($autoloaderFile).'/composer/autoload_psr4.php';

        if (!file_exists($psr4File)) {
            return $dirs;
        }

        $psr4 = include $psr4File;
        $namespaceWithBackslash = $namespace.'\\';

        // Find case-insensitive match for the namespace
        foreach ($psr4 as $prefix => $paths) {
            if (strtolower($prefix) === strtolower($namespaceWithBackslash)) {
                $dirs = array_merge($dirs, (array) $paths);
            }
        }

        return $dirs;
    }
}
