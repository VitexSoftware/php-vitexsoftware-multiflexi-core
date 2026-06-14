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
 * Task — one scheduling window for a RunTemplate.
 *
 * A RunTemplate produces one Task per interval tick. A Task is fulfilled when
 * the first successful Job completes within the window (or before the deadline).
 * Additional Jobs are spawned as retries until the budget is exhausted.
 *
 * State machine:
 *   open → running → fulfilled          (success on time)
 *                  → fulfilled_late     (success after deadline, allow_late=true)
 *                  → open              (retry: failed job, budget remains)
 *   open → missed                       (window_end reached, zero attempts)
 *   running → failed                   (budget exhausted or window expired)
 */
class Task extends DBEngine
{
    public const STATE_OPEN = 'open';
    public const STATE_RUNNING = 'running';
    public const STATE_FULFILLED = 'fulfilled';
    public const STATE_FULFILLED_LATE = 'fulfilled_late';
    public const STATE_FAILED = 'failed';
    public const STATE_MISSED = 'missed';

    public function __construct($identifier = null, array $options = [])
    {
        $this->myTable = 'task';
        $this->nameColumn = 'state';
        parent::__construct($identifier, $options);
    }

    /**
     * Create (materialize) a Task row for the given RunTemplate scheduling window.
     *
     * Computes window_end and deadline from the RunTemplate configuration.
     * Validates retry config: warns and caps max_attempts if min_gap * N > budget.
     */
    public static function materialize(RunTemplate $rt, \DateTime $windowStart): self
    {
        $interv = $rt->getDataValue('interv');
        $intervalSeconds = (int) Scheduler::codeToSeconds($interv);

        $windowEnd = clone $windowStart;
        $windowEnd->modify('+'.$intervalSeconds.' seconds');

        $deadlineOffset = $rt->getDataValue('deadline_offset');
        $deadline = self::computeDeadline($windowStart, $windowEnd, $deadlineOffset);

        $maxAttempts = max(1, (int) ($rt->getDataValue('max_attempts') ?? 1));
        $retryMinGap = max(0, (int) ($rt->getDataValue('retry_min_gap') ?? 0));

        $budget = $deadline->getTimestamp() - $windowStart->getTimestamp();

        if ($retryMinGap > 0 && $maxAttempts > 1 && ($retryMinGap * $maxAttempts) > $budget) {
            $maxAttempts = max(1, (int) floor($budget / $retryMinGap));
            $rt->addStatusMessage(sprintf(
                _('retry_min_gap * max_attempts exceeds deadline budget; capping max_attempts to %d'),
                $maxAttempts,
            ), 'warning');
        }

        $task = new self();
        $task->setData([
            'runtemplate_id' => $rt->getMyKey(),
            'window_start' => $windowStart->format('Y-m-d H:i:s'),
            'window_end' => $windowEnd->format('Y-m-d H:i:s'),
            'deadline' => $deadline->format('Y-m-d H:i:s'),
            'state' => self::STATE_OPEN,
            'fulfilled_by_job_id' => null,
            'fulfilled_at' => null,
            'attempts' => 0,
            'created_at' => (new \DateTime())->format('Y-m-d H:i:s'),
        ], true);

        $taskId = $task->insertToSQL();
        $task->loadFromSQL($taskId);

        return $task;
    }

    /**
     * Parse deadline_offset and return the effective deadline DateTime.
     *
     * Supported formats:
     *  - null or empty  → window_end
     *  - "+Xh", "+Xm", "+Xs" → window_start + offset
     *  - "HH:MM"            → absolute time-of-day on the window_start date
     */
    private static function computeDeadline(\DateTime $windowStart, \DateTime $windowEnd, ?string $offset): \DateTime
    {
        if (empty($offset)) {
            return clone $windowEnd;
        }

        if (str_starts_with($offset, '+')) {
            $deadline = clone $windowStart;
            $deadline->modify($offset);

            return $deadline;
        }

        if (preg_match('/^\d{1,2}:\d{2}$/', $offset)) {
            [$h, $m] = explode(':', $offset);
            $deadline = clone $windowStart;
            $deadline->setTime((int) $h, (int) $m, 0);

            return $deadline;
        }

        return clone $windowEnd;
    }

    /**
     * Mark this task as having an active running job.
     */
    public function markRunning(): void
    {
        $this->updateToSQL(
            ['state' => self::STATE_RUNNING],
            ['id' => $this->getMyKey()],
        );
        $this->setDataValue('state', self::STATE_RUNNING);
    }

    /**
     * Mark this task as fulfilled by the given successful Job.
     *
     * Uses fulfilled_late state when the success arrived after the deadline
     * and the RunTemplate has allow_late=true.
     */
    public function fulfill(Job $job): void
    {
        $now = new \DateTime();
        $deadline = new \DateTime((string) $this->getDataValue('deadline'));
        $rt = new RunTemplate((int) $this->getDataValue('runtemplate_id'));

        if ($now > $deadline && (bool) $rt->getDataValue('allow_late')) {
            $state = self::STATE_FULFILLED_LATE;
        } else {
            $state = self::STATE_FULFILLED;
        }

        $this->updateToSQL([
            'state' => $state,
            'fulfilled_by_job_id' => $job->getMyKey(),
            'fulfilled_at' => $now->format('Y-m-d H:i:s'),
        ], ['id' => $this->getMyKey()]);
        $this->setDataValue('state', $state);
    }

    /**
     * Mark this task as failed (budget exhausted or window expired with at least one attempt).
     */
    public function markFailed(): void
    {
        $this->updateToSQL(
            ['state' => self::STATE_FAILED],
            ['id' => $this->getMyKey()],
        );
        $this->setDataValue('state', self::STATE_FAILED);
    }

    /**
     * Mark this task as missed (window expired with zero attempts — scheduler was down).
     */
    public function markMissed(): void
    {
        $this->updateToSQL(
            ['state' => self::STATE_MISSED],
            ['id' => $this->getMyKey()],
        );
        $this->setDataValue('state', self::STATE_MISSED);
    }

    /**
     * Increment the attempt counter in the database.
     */
    public function incrementAttempts(): void
    {
        $attempts = (int) $this->getDataValue('attempts') + 1;
        $this->updateToSQL(
            ['attempts' => $attempts, 'state' => self::STATE_OPEN],
            ['id' => $this->getMyKey()],
        );
        $this->setDataValue('attempts', $attempts);
        $this->setDataValue('state', self::STATE_OPEN);
    }

    /**
     * Whether another job attempt can be made for this task.
     */
    public function canRetry(): bool
    {
        if ($this->isWindowExpired()) {
            return false;
        }

        $rt = new RunTemplate((int) $this->getDataValue('runtemplate_id'));
        $maxAttempts = max(1, (int) ($rt->getDataValue('max_attempts') ?? 1));

        return (int) $this->getDataValue('attempts') < $maxAttempts;
    }

    /**
     * Compute when the next retry job should be scheduled.
     *
     * Returns null if no retry is possible.
     */
    public function getNextRetryTime(): ?\DateTime
    {
        if (!$this->canRetry()) {
            return null;
        }

        $rt = new RunTemplate((int) $this->getDataValue('runtemplate_id'));
        $minGap = max(0, (int) ($rt->getDataValue('retry_min_gap') ?? 0));
        $backoff = (string) ($rt->getDataValue('retry_backoff') ?? 'none');
        $attempts = (int) $this->getDataValue('attempts');

        $delay = match ($backoff) {
            'exponential' => $minGap * (2 ** $attempts),
            'linear' => $minGap * $attempts,
            'fixed' => $minGap,
            default => $minGap,
        };

        $next = new \DateTime();
        $next->modify('+'.$delay.' seconds');

        $deadline = new \DateTime((string) $this->getDataValue('deadline'));

        return $next <= $deadline ? $next : null;
    }

    /**
     * Whether the current time is past the task deadline.
     */
    public function isOverDeadline(): bool
    {
        return new \DateTime() > new \DateTime((string) $this->getDataValue('deadline'));
    }

    /**
     * Whether the scheduling window has expired.
     */
    public function isWindowExpired(): bool
    {
        return new \DateTime() > new \DateTime((string) $this->getDataValue('window_end'));
    }

    /**
     * Get all Jobs belonging to this task.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getJobs(): array
    {
        $jobber = new Job();

        return $jobber->listingQuery()->where(['task_id' => $this->getMyKey()])->orderBy('id ASC')->fetchAll();
    }

    /**
     * Find the current open/running Task for a RunTemplate window, or null.
     */
    public static function findForWindow(int $runtemplateId, \DateTime $windowStart): ?self
    {
        $task = new self();
        $row = $task->listingQuery()
            ->where('runtemplate_id', $runtemplateId)
            ->where('window_start', $windowStart->format('Y-m-d H:i:s'))
            ->fetch();

        if (empty($row)) {
            return null;
        }

        return new self((int) $row['id']);
    }

    /**
     * Finalize stale open/running tasks whose window has expired.
     * Called by the scheduler cleanup pass.
     */
    public static function finalizeExpired(): void
    {
        $task = new self();
        $stale = $task->listingQuery()
            ->where('state IN (?, ?)', [self::STATE_OPEN, self::STATE_RUNNING])
            ->where('window_end < NOW()')
            ->fetchAll();

        foreach ($stale as $row) {
            $t = new self((int) $row['id']);

            if ((int) $row['attempts'] === 0) {
                $t->markMissed();
            } else {
                $t->markFailed();
            }
        }
    }
}
