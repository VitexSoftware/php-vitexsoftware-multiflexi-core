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

namespace MultiFlexi\Action;

/**
 * Description of RedmineIssue.
 *
 * @author vitex
 *
 * @no-named-arguments
 */
class ToDo extends \MultiFlexi\CommonAction
{
    /**
     * Module Caption.
     *
     * @return string
     */
    public static function name()
    {
        return _('ToDo Issue');
    }

    /**
     * Module Description.
     *
     * @return string
     */
    public static function description()
    {
        return _('Make ToDo issue using Job output');
    }

    /**
     * Is this Action Suitable for Application.
     *
     * @param \MultiFlexi\Application $app
     */
    public static function usableForApp($app): bool
    {
        return \is_object($app);
    }

    /**
     * Create new task in Microsoft To Do.
     *
     * @param string $accessToken Access token for Microsoft Graph API
     * @param string $listId      Task list ID
     * @param string $title       Task title
     * @param string $importance  Task importance (low, normal, high)
     * @param string $dueDateTime Due date and time (ISO 8601 format)
     * @param string $timeZone    Time zone (e.g. Europe/Prague)
     *
     * @return null|array Newly created task or null on error
     */
    public function createToDoTask(
        string $accessToken,
        string $listId,
        string $title,
        string $importance = 'normal',
        ?string $dueDateTime = null,
        string $timeZone = 'Europe/Prague',
    ): ?array {
        // Build request body
        $taskData = [
            'title' => $title,
            'importance' => $importance,
        ];

        // Add due date if specified
        if ($dueDateTime) {
            $taskData['dueDateTime'] = [
                'dateTime' => $dueDateTime,
                'timeZone' => $timeZone,
            ];
        }

        // Convert to JSON
        $jsonData = json_encode($taskData, \JSON_UNESCAPED_UNICODE);

        // Initialize cURL for creating task
        $ch = curl_init();

        // URL endpoint for creating task in specific list
        $url = 'https://graph.microsoft.com/v1.0/me/todo/lists/'.$listId.'/tasks';

        // Set cURL parameters
        curl_setopt_array($ch, [
            \CURLOPT_URL => $url,
            \CURLOPT_RETURNTRANSFER => true,
            \CURLOPT_POST => true,
            \CURLOPT_POSTFIELDS => $jsonData,
            \CURLOPT_HTTPHEADER => [
                'Authorization: Bearer '.$accessToken,
                'Content-Type: application/json',
                'Content-Length: '.\strlen($jsonData),
            ],
        ]);

        // Execute HTTP POST request
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, \CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        // Check for cURL errors
        if ($curlError) {
            $this->addStatusMessage('cURL error while creating task: '.$curlError, 'error');

            return null;
        }

        // Check HTTP status code (201 = Created)
        if ($httpCode !== 201) {
            $errorInfo = json_decode($response, true);
            $errorMessage = $errorInfo['error']['message'] ?? 'Unknown error';
            $this->addStatusMessage(
                sprintf('HTTP error %d while creating task: %s', $httpCode, $errorMessage),
                'error',
            );

            return null;
        }

        // Decode JSON response with newly created task
        $taskResult = json_decode($response, true);

        if (!$taskResult) {
            $this->addStatusMessage('Invalid response while creating task', 'error');

            return null;
        }

        return $taskResult;
    }

    /**
     * Perform action - create Microsoft To Do task based on job output.
     *
     * @param \MultiFlexi\Job $job Job instance
     */
    public function perform(\MultiFlexi\Job $job): void
    {
        // Get configuration from action
        $accessToken = $this->getDataValue('access_token');
        $listId = $this->getDataValue('list_id');
        $taskTitle = $this->getDataValue('task_title');
        $importance = $this->getDataValue('importance') ?: 'normal';
        $dueDateTime = $this->getDataValue('due_datetime');

        // Check required parameters
        if (empty($accessToken)) {
            $this->addStatusMessage('Missing access token for Microsoft Graph API', 'error');

            return;
        }

        if (empty($listId)) {
            $this->addStatusMessage('Missing task list ID', 'error');

            return;
        }

        // Create task title based on job if not specified
        if (empty($taskTitle)) {
            $exitCode = (int) $job->getDataValue('exitcode');
            $appName = $this->runtemplate->application->getRecordName();
            $taskTitle = $appName.' - '.($exitCode === 0 ? 'Completed' : 'Error');
        }

        // Replace placeholders in task title
        $taskTitle = $this->replacePlaceholders($taskTitle, $job);

        // Convert datetime to proper format if specified
        $formattedDateTime = null;

        if (!empty($dueDateTime)) {
            try {
                $dateObj = new \DateTime($dueDateTime);
                $formattedDateTime = $dateObj->format('Y-m-d\TH:i:s.0000000');
            } catch (\Exception $e) {
                $this->addStatusMessage('Invalid date format: '.$e->getMessage(), 'warning');
            }
        }

        // Create task in Microsoft To Do
        $result = $this->createToDoTask(
            $accessToken,
            $listId,
            $taskTitle,
            $importance,
            $formattedDateTime,
        );

        // Process result
        if ($result) {
            $this->addStatusMessage(
                sprintf(
                    'Task "%s" was successfully created in Microsoft To Do (ID: %s)',
                    $result['title'],
                    $result['id'],
                ),
                'success',
            );
        } else {
            $this->addStatusMessage('Failed to create task in Microsoft To Do', 'error');
        }
    }

    /**
     * Initial data for action configuration.
     *
     * @param string $mode Mode
     *
     * @return array Default configuration
     */
    public function initialData(string $mode): array
    {
        return [
            'access_token' => '',
            'list_id' => '',
            'task_title' => '{APP_NAME} - Job #{JOB_ID}',
            'importance' => 'normal',
            'due_datetime' => '',
        ];
    }

    /**
     * Get Office365 access token using client credentials flow.
     *
     * @param array $credentialData Office365 credential data
     *
     * @return null|string Access token or null on failure
     */
    protected function getAccessToken(array $credentialData): ?string
    {
        $tenant = $credentialData['OFFICE365_TENANT'] ?? '';
        $clientId = $credentialData['OFFICE365_CLIENTID'] ?? '';
        $clientSecret = $credentialData['OFFICE365_CLSECRET'] ?? '';

        if (empty($tenant) || empty($clientId) || empty($clientSecret)) {
            $this->addStatusMessage('Missing required Office365 credentials', 'error');

            return null;
        }

        // OAuth2 endpoint for getting access token
        $tokenUrl = "https://login.microsoftonline.com/{$tenant}/oauth2/v2.0/token";

        $postData = [
            'grant_type' => 'client_credentials',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'scope' => 'https://graph.microsoft.com/.default',
        ];

        // Initialize cURL for token request
        $ch = curl_init();

        curl_setopt_array($ch, [
            \CURLOPT_URL => $tokenUrl,
            \CURLOPT_POST => true,
            \CURLOPT_POSTFIELDS => http_build_query($postData),
            \CURLOPT_RETURNTRANSFER => true,
            \CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, \CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            $this->addStatusMessage('cURL error while getting token: '.$curlError, 'error');

            return null;
        }

        if ($httpCode !== 200) {
            $this->addStatusMessage('HTTP error while getting token: '.$httpCode, 'error');

            return null;
        }

        $tokenData = json_decode($response, true);

        if (!$tokenData || !isset($tokenData['access_token'])) {
            $this->addStatusMessage('Invalid token response', 'error');

            return null;
        }

        return $tokenData['access_token'];
    }

    /**
     * Determine priority based on exit code.
     *
     * @param int $exitCode Job exit code
     *
     * @return string Priority level
     */
    private static function determinePriority(int $exitCode): string
    {
        if ($exitCode === 0) {
            return 'low';  // Success
        }

        if ($exitCode < 10) {
            return 'medium';  // Minor errors
        }

        if ($exitCode < 100) {
            return 'high';  // Major errors
        }

        return 'critical';  // Critical errors
    }

    /**
     * Build detailed description for the ToDo item.
     *
     * @param \MultiFlexi\Job $job Job instance
     *
     * @return string Description
     */
    private function buildDescription(\MultiFlexi\Job $job): string
    {
        $description = [];

        $description[] = '## Job Details';
        $description[] = 'Job ID: '.$job->getMyKey();
        $description[] = 'Command: '.$job->getDataValue('command');
        $description[] = 'Exit Code: '.$job->getDataValue('exitcode');
        $description[] = 'Executed: '.$job->getDataValue('begin');
        $description[] = '';

        // Add stdout if present
        $stdout = $job->getDataValue('stdout');

        if (!empty($stdout)) {
            $description[] = '## Output (stdout)';
            $description[] = '```';
            $description[] = stripslashes($stdout);
            $description[] = '```';
            $description[] = '';
        }

        // Add stderr if present
        $stderr = $job->getDataValue('stderr');

        if (!empty($stderr)) {
            $description[] = '## Error Output (stderr)';
            $description[] = '```';
            $description[] = stripslashes($stderr);
            $description[] = '```';
            $description[] = '';
        }

        // Add system info
        $description[] = '## System Information';
        $description[] = 'MultiFlexi: '.\Ease\Shared::appName().' '.\Ease\Shared::appVersion();
        $description[] = 'Application: '.$this->runtemplate->application->getRecordName();
        $description[] = 'Company: '.$this->runtemplate->getCompany()->getRecordName();
        $description[] = 'RunTemplate: '.$this->runtemplate->getRecordName();

        return implode("\n", $description);
    }

    /**
     * Replace placeholders in text with values from job.
     *
     * @param string          $text Text with placeholders
     * @param \MultiFlexi\Job $job  Job instance
     *
     * @return string Text with replaced placeholders
     */
    private function replacePlaceholders(string $text, \MultiFlexi\Job $job): string
    {
        $replacements = [
            '{JOB_ID}' => $job->getMyKey(),
            '{EXIT_CODE}' => $job->getDataValue('exitcode'),
            '{COMMAND}' => $job->getDataValue('command'),
            '{APP_NAME}' => $this->runtemplate->application->getRecordName(),
            '{COMPANY_NAME}' => $this->runtemplate->getCompany()->getRecordName(),
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $text);
    }
}
