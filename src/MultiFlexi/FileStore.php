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

use Ease\SQL\Engine;

/**
 * Description of FileStore.
 *
 * @autor Vitex <info@vitexsoftware.cz>
 */
class FileStore extends Engine
{
    public function __construct($identifier = null, $options = [])
    {
        $this->myTable = 'file_store';
        parent::__construct($identifier, $options);
    }

    /**
     * Load a file from the file system and store it in the database.
     */
    public function loadAndStoreFile(string $field, string $filePath, string $fileName, ?RunTemplate $runtemplate = null, ?Job $job = null): bool
    {
        if (!file_exists($filePath)) {
            return false;
        }

        $fileData = file_get_contents($filePath);

        if ($fileData === false) {
            return false;
        }

        $data = [
            'file_name' => $fileName,
            'file_path' => $filePath,
            'file_data' => $fileData,
            'field' => $field,
            'runtemplate_id' => $runtemplate ? $runtemplate->getMyKey() : '',
            'job_id' => $job ? $job->getMyKey() : '',
            'created_at' => date('Y-m-d H:i:s'),
        ];

        // Check if a record with the same filename and job_id exists
        $existingRecord = $this->getColumnsFromSQL(['id'], ['field' => $field, 'job_id' => $job ? $job->getMyKey() : '']);

        if ($existingRecord && \array_key_exists('id', $existingRecord)) {
            // Update the existing record
            $result = $this->updateToSQL($data, ['id' => $existingRecord['id']]);
        } else {
            // Insert a new record
            $result = $this->insertToSQL($data);
        }

        if ($job) {
            $configFields = new ConfigFields($fileName);
            $configFields->addField((new ConfigField($field, 'file-path', $fileName, $filePath, '', $filePath))->setSource(\Ease\Euri::fromObject($this)));
            $job->updateEnvironment($configFields);
        }

        return $result ? unlink($filePath) : false;
    }

    /**
     * Store a file related to a RunTemplate.
     */
    public function storeFileForRuntemplate(string $field, string $filePath, string $fileName, RunTemplate $runtemplate): bool
    {
        return $this->loadAndStoreFile($field, $filePath, $fileName, $runtemplate->getMyKey(), null);
    }

    /**
     * Store a file related to a job.
     */
    public function storeFileForJob(string $field, string $filePath, string $fileName, Job $job): bool
    {
        return $this->loadAndStoreFile($field, $filePath, $fileName, $job->getRunTemplate(), $job);
    }

    /**
     * Extract files for a given job and return its list in format field => path/filename.
     */
    public function extractFilesForJob(\MultiFlexi\Job $job): ConfigFields
    {
        $files = new ConfigFields();
        $jobId = $job->getMyKey();

        $records = $this->getColumnsFromSQL(['id', 'field', 'file_path', 'file_name', 'file_data'], ['job_id' => $jobId]);

        foreach ($records as $record) {
            $this->setData($record);

            if ($this->restoreFile()) {
                $fileField = new ConfigField($record['field'], 'file-path', $record['field'], _('uploaded file'), '', $record['file_path'].'_'.$record['file_name']);
                $fileField->setSource(\Ease\Euri::fromObject($this));
                $files->addField($fileField);
            }
        }

        return $files;
    }

    public function restoreFile(): int
    {
        $record = $this->getData();

        return file_put_contents($record['file_path'].'_'.$record['file_name'], $record['file_data']);
    }
}
