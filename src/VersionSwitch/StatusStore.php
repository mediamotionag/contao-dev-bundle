<?php
/**
 * @copyright  Media Motion AG <https://www.mediamotion.ch>
 * @author     Ali Gueler (Media Motion AG)
 * @package    MemoDevBundle
 * @license    LGPL-3.0+
 * @see        https://github.com/mediamotionag/contao-dev-bundle
 */

namespace Memo\DevBundle\VersionSwitch;

use Symfony\Component\Filesystem\Filesystem;

/**
 * Per run: <id>.json (status), <id>.log (step log written by the job), <id>.out (raw stdout/stderr, catches fatals).
 * switch.lock is held by the running job; a run marked running without the lock has died.
 */
class StatusStore
{
    public const STATE_QUEUED = 'queued';
    public const STATE_RUNNING = 'running';
    public const STATE_SUCCESS = 'success';
    public const STATE_FAILED_UNCHANGED = 'failed_unchanged';
    public const STATE_FAILED_REVERTED = 'failed_reverted';
    public const STATE_FAILED = 'failed';
    public const STATE_ABORTED = 'aborted';

    private const RUN_ID_PATTERN = '/^\d{8}-\d{6}-[a-f0-9]{6}$/';
    private const KEEP_RUNS = 10;
    private const QUEUE_GRACE_SECONDS = 60;

    private string $dir;

    private Filesystem $filesystem;

    /** @var resource|null */
    private $lockHandle;

    public function __construct(private readonly string $projectDir)
    {
        $this->dir = $projectDir . '/var/memo-dev/version-switch';
        $this->filesystem = new Filesystem();
    }

    public function createRunId(): string
    {
        return date('Ymd-His') . '-' . bin2hex(random_bytes(3));
    }

    public function isValidRunId(string $runId): bool
    {
        return 1 === preg_match(self::RUN_ID_PATTERN, $runId);
    }

    public function write(string $runId, array $status): void
    {
        $this->filesystem->dumpFile($this->getPath($runId, 'json'), json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    public function read(string $runId): ?array
    {
        $path = $this->getPath($runId, 'json');

        if (!is_file($path)) {
            return null;
        }

        $status = json_decode((string) file_get_contents($path), true);

        return \is_array($status) ? $status : null;
    }

    public function appendLog(string $runId, string $text): void
    {
        $this->filesystem->appendToFile($this->getPath($runId, 'log'), $text);
    }

    public function getPath(string $runId, string $extension): string
    {
        if (!$this->isValidRunId($runId)) {
            throw new \InvalidArgumentException(sprintf('Invalid run ID "%s".', $runId));
        }

        return $this->dir . '/' . $runId . '.' . $extension;
    }

    /**
     * A run including its log tail, with a dead job reported as aborted.
     */
    public function getRun(string $runId): ?array
    {
        if (!$this->isValidRunId($runId) || null === ($status = $this->read($runId))) {
            return null;
        }

        $status = $this->withLiveState($status);
        // Size first: bytes appended after the tail was read would be skipped by the next poll
        $status['logOffset'] = $this->getLogSize($runId);
        $status['log'] = $this->tail($this->getPath($runId, 'log'), 60000, $status['logOffset']);

        if (self::STATE_ABORTED === $status['state']) {
            $status['log'] .= "\n" . $this->tail($this->getPath($runId, 'out'), 5000);
        }

        return $status;
    }

    /**
     * The status without log, for polling: the log is read incrementally via readLog().
     */
    public function getRunState(string $runId): ?array
    {
        if (!$this->isValidRunId($runId) || null === ($status = $this->read($runId))) {
            return null;
        }

        return $this->withLiveState($status);
    }

    /**
     * @return array{log: string, offset: int} what was appended to the log since $offset
     */
    public function readLog(string $runId, int $offset, int $maxBytes = 200000): array
    {
        $path = $this->getPath($runId, 'log');
        $size = $this->getLogSize($runId);

        // A smaller file than the client has seen means it was replaced: start over
        if ($offset > $size) {
            $offset = 0;
        }

        $offset = max($offset, $size - $maxBytes);
        $log = $size > $offset ? (string) file_get_contents($path, false, null, $offset, $size - $offset) : '';

        return ['log' => $log, 'offset' => $size];
    }

    public function getLatestRun(): ?array
    {
        $runIds = $this->getRunIds();

        return [] !== $runIds ? $this->getRun($runIds[0]) : null;
    }

    /**
     * @return list<array> newest first, without logs
     */
    public function getRuns(): array
    {
        $runs = [];

        foreach ($this->getRunIds() as $runId) {
            if (null !== ($status = $this->read($runId))) {
                $runs[] = $this->withLiveState($status);
            }
        }

        return $runs;
    }

    /**
     * The latest finished run that changed the checkout; a rollback returns to its previousRef.
     */
    public function getRollbackTarget(): ?array
    {
        foreach ($this->getRuns() as $run) {
            if ($run['running'] || !\in_array($run['action'] ?? 'switch', ['switch', 'rollback'], true) || empty($run['previousRef'])) {
                continue;
            }

            // A run from a version to the same version changed nothing to roll back
            if (RunFormatter::displayRef($run['previousRef']) === RunFormatter::displayRef((string) ($run['tag'] ?? ''))) {
                continue;
            }

            if (self::STATE_SUCCESS === $run['state'] || (\in_array($run['state'], [self::STATE_FAILED, self::STATE_ABORTED], true) && ($run['checkedOut'] ?? false))) {
                return $run;
            }
        }

        return null;
    }

    public function getRestoreBackup(): ?string
    {
        return $this->getRestoreState()['backup'];
    }

    /**
     * Only offered once the code is back on the old version: a rollback offers the backup of the switch it
     * rolled back, a reverted switch its own. Older backups belong to older code. A restored backup is not offered again.
     *
     * @return array{backup: ?string, reason: ?string} reason (none, no_migration, restored, missing) when there is no backup
     */
    public function getRestoreState(): array
    {
        $runs = $this->getRuns();
        $byId = array_column($runs, null, 'runId');
        $restored = [];

        foreach ($runs as $run) {
            $action = $run['action'] ?? 'switch';

            if ($run['running'] || self::STATE_FAILED_UNCHANGED === $run['state']) {
                continue;
            }

            if ('restore' === $action) {
                if (self::STATE_SUCCESS === $run['state']) {
                    $restored[] = $run['backup'] ?? '';
                }

                continue;
            }

            $codeIsOld = 'rollback' === $action
                ? self::STATE_SUCCESS === $run['state']
                : self::STATE_FAILED_REVERTED === $run['state'];

            if (!$codeIsOld) {
                return ['backup' => null, 'reason' => 'none'];
            }

            $backup = 'rollback' === $action
                ? ($byId[$run['rollbackOf'] ?? '']['backup'] ?? $run['backup'] ?? null)
                : ($run['backup'] ?? null);

            return match (true) {
                empty($backup) => ['backup' => null, 'reason' => 'no_migration'],
                \in_array($backup, $restored, true) => ['backup' => null, 'reason' => 'restored'],
                !is_file($this->projectDir . '/var/backups/' . basename($backup)) => ['backup' => null, 'reason' => 'missing'],
                default => ['backup' => $backup, 'reason' => null],
            };
        }

        return ['backup' => null, 'reason' => 'none'];
    }

    /**
     * Seconds per step, averaged over the last successful runs of the same kind (restore vs. switch/rollback).
     *
     * @return array<string, float>
     */
    public function getAverageStepDurations(string $action): array
    {
        $durations = [];
        $used = 0;
        $kind = 'restore' === $action ? 'restore' : 'switch';

        foreach ($this->getRuns() as $run) {
            $runKind = 'restore' === ($run['action'] ?? 'switch') ? 'restore' : 'switch';

            if ($runKind !== $kind || self::STATE_SUCCESS !== $run['state'] || empty($run['stepTimes'])) {
                continue;
            }

            $starts = $run['stepTimes'];
            $keys = array_keys($starts);
            $end = (float) strtotime((string) ($run['finishedAt'] ?? 'now'));

            foreach ($keys as $i => $key) {
                $next = isset($keys[$i + 1]) ? $starts[$keys[$i + 1]] : $end;
                $durations[$key][] = max(1.0, $next - $starts[$key]);
            }

            if (++$used >= 5) {
                break;
            }
        }

        return array_map(static fn (array $values): float => array_sum($values) / \count($values), $durations);
    }

    public function markAborted(string $runId, string $message): void
    {
        if (null === ($status = $this->read($runId))) {
            return;
        }

        $status['state'] = self::STATE_ABORTED;
        $status['message'] = $message;
        $status['finishedAt'] = date(DATE_ATOM);
        $this->write($runId, $status);
        $this->appendLog($runId, sprintf("[%s] ABORTED: %s\n", date('H:i:s'), $message));
    }

    public function lock(): bool
    {
        $this->filesystem->mkdir($this->dir);
        $handle = fopen($this->dir . '/switch.lock', 'c');

        if (false === $handle) {
            return false;
        }

        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);

            return false;
        }

        $this->lockHandle = $handle;

        return true;
    }

    public function isLocked(): bool
    {
        if (null !== $this->lockHandle) {
            return true;
        }

        if (!$this->lock()) {
            return true;
        }

        flock($this->lockHandle, LOCK_UN);
        fclose($this->lockHandle);
        $this->lockHandle = null;

        return false;
    }

    public function prune(): void
    {
        foreach (\array_slice($this->getRunIds(), self::KEEP_RUNS) as $runId) {
            $this->filesystem->remove([$this->getPath($runId, 'json'), $this->getPath($runId, 'log'), $this->getPath($runId, 'out')]);
        }
    }

    private function withLiveState(array $status): array
    {
        $active = \in_array($status['state'] ?? '', [self::STATE_QUEUED, self::STATE_RUNNING], true);

        if ($active && !$this->isLocked()) {
            $age = time() - (int) strtotime((string) ($status['startedAt'] ?? 'now'));

            if (self::STATE_RUNNING === $status['state'] || $age > self::QUEUE_GRACE_SECONDS) {
                $status['state'] = self::STATE_ABORTED;
                $active = false;
            }
        }

        $status['running'] = $active;

        return $status;
    }

    /**
     * @return list<string> newest first
     */
    private function getRunIds(): array
    {
        $runIds = array_map(
            static fn (string $path): string => basename($path, '.json'),
            glob($this->dir . '/*.json') ?: []
        );

        $runIds = array_values(array_filter($runIds, fn (string $runId): bool => $this->isValidRunId($runId)));
        rsort($runIds);

        return $runIds;
    }

    private function getLogSize(string $runId): int
    {
        $path = $this->getPath($runId, 'log');
        clearstatcache(true, $path);

        return is_file($path) ? (int) filesize($path) : 0;
    }

    private function tail(string $path, int $bytes = 60000, ?int $size = null): string
    {
        if (!is_file($path)) {
            return '';
        }

        $size ??= (int) filesize($path);
        $content = $size > 0 ? (string) file_get_contents($path, false, null, max(0, $size - $bytes), min($size, $bytes)) : '';

        return $size > $bytes ? '…' . $content : $content;
    }
}
