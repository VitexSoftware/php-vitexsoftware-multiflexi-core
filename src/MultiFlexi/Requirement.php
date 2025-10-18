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
        $forms = [];
        $namespace = 'MultiFlexi\CredentialType';

        // Get current loaded classes in namespace
        $alreadyLoaded = [];

        foreach (get_declared_classes() as $class) {
            if (str_starts_with($class, $namespace.'\\')) {
                $parts = explode('\\', $class);
                $className = end($parts);
                $forms[$className] = $class;
                $alreadyLoaded[strtolower($className)] = true;
            }
        }

        // Find PSR-4 autoload directories for the namespace
        $dirs = self::getPsr4DirsForNamespace($namespace);
        $processedFiles = [];

        // Process each directory
        foreach ($dirs as $dir) {
            if (!is_dir($dir) || !is_readable($dir)) {
                continue;
            }

            // Process PHP files in the directory
            foreach (new \DirectoryIterator($dir) as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $basename = $file->getBasename('.php');
                    $filePath = $file->getRealPath();

                    // Skip already processed files by basename (case insensitive)
                    $fileKey = strtolower($basename);

                    if (isset($processedFiles[$fileKey]) || isset($alreadyLoaded[$fileKey])) {
                        continue;
                    }

                    $processedFiles[$fileKey] = true;

                    // Try PSR-4 autoloading first
                    $className = $namespace.'\\'.$basename;

                    if (!class_exists($className, false)) {
                        // Attempt to autoload the class
                        $wasLoaded = @class_exists($className, true);

                        if ($wasLoaded) {
                            $parts = explode('\\', $className);
                            $shortName = end($parts);
                            $forms[$shortName] = $className;
                        }
                    }
                }
            }
        }

        // If no classes were found, fallback to the original method
        if (empty($forms)) {
            foreach (\Ease\Functions::classesInNamespace($namespace) as $form) {
                $forms[$form] = '\\'.$namespace.'\\'.$form;
            }
        }

        return $forms;
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
