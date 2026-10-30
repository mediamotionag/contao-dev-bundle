<?php
/**
 * @copyright  Media Motion AG <https://www.mediamotion.ch>
 * @author     Ali Gueler (Media Motion AG)
 * @package    MemoDevBundle
 * @license    LGPL-3.0+
 * @see        https://github.com/mediamotionag/contao-dev-bundle
 */

namespace Memo\DevBundle\VersionSwitch;

use Symfony\Component\Process\Process;

class SwitchLauncher
{
    // CGI variables of the current request must not leak into the console kernel of the job
    private const REQUEST_ENV_PATTERN = '/^(HTTP_|HTTPS$|REDIRECT_|REQUEST_|SCRIPT_|SERVER_|REMOTE_|DOCUMENT_|CONTEXT_|CONTENT_|GATEWAY_INTERFACE$|QUERY_STRING$|PATH_INFO$|PATH_TRANSLATED$|PHP_SELF$|ORIG_|FCGI_ROLE$)/';

    public function __construct(
        private readonly string $projectDir,
        private readonly GitRepository $git,
        private readonly BinaryResolver $binaries,
        private readonly StatusStore $store,
    ) {
    }

    /**
     * Checks everything that can be checked without changing the installation.
     *
     * @return list<string> reasons why a job cannot start, empty when it can
     */
    public function getBlockers(bool $needsCleanCheckout = true): array
    {
        $blockers = [];

        if (!\function_exists('proc_open')) {
            return ['proc_open() is disabled on this server.'];
        }

        if (!$this->git->isRepository()) {
            return [sprintf('%s is not a git checkout.', $this->projectDir)];
        }

        if ($this->store->isLocked()) {
            $blockers[] = 'A job is already running.';
        }

        if ($needsCleanCheckout && [] !== $this->git->getModifiedFiles()) {
            $blockers[] = 'The checkout has modified tracked files.';
        }

        try {
            $cliVersion = $this->binaries->getPhpVersion();
            $webVersion = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;

            if ($cliVersion !== $webVersion) {
                $blockers[] = sprintf('CLI PHP %s (%s) differs from the web PHP %s. Set MEMO_DEV_PHP_BINARY.', $cliVersion, $this->binaries->getPhp(), $webVersion);
            }

            $this->binaries->getComposerCommand();
        } catch (\RuntimeException $e) {
            $blockers[] = $e->getMessage();
        }

        return $blockers;
    }

    public function launchSwitch(string $tag): string
    {
        if (!$this->git->tagExists($tag)) {
            throw new \InvalidArgumentException(sprintf('Tag "%s" does not exist.', $tag));
        }

        // The browser checks this too, but against the version at page load
        if ($this->git->getCurrentRef()['tag'] === $tag) {
            throw new \RuntimeException(sprintf('%s is already checked out.', $tag));
        }

        return $this->launch(['memo:dev:switch-tag', $tag], ['action' => 'switch', 'tag' => $tag]);
    }

    public function launchRollback(): string
    {
        if (null === ($target = $this->store->getRollbackTarget())) {
            throw new \RuntimeException('There is no switch to roll back.');
        }

        return $this->launch(['memo:dev:switch-tag', '--rollback'], ['action' => 'rollback', 'tag' => $target['previousRef']]);
    }

    public function launchRestore(): string
    {
        if (null === ($backup = $this->store->getRestoreBackup())) {
            throw new \RuntimeException('There is no backup of a switch to restore.');
        }

        return $this->launch(['memo:dev:restore-backup', $backup], ['action' => 'restore', 'tag' => $backup, 'backup' => $backup], false);
    }

    /**
     * @param list<string> $args
     */
    private function launch(array $args, array $status, bool $needsCleanCheckout = true): string
    {
        if ([] !== ($blockers = $this->getBlockers($needsCleanCheckout))) {
            throw new \RuntimeException(implode(' ', $blockers));
        }

        $runId = $this->store->createRunId();

        $this->store->write($runId, $status + [
            'runId' => $runId,
            'state' => StatusStore::STATE_QUEUED,
            'step' => null,
            'startedAt' => date(DATE_ATOM),
        ]);

        $command = [
            $this->binaries->getPhp(),
            $this->projectDir . '/vendor/bin/contao-console',
            ...$args,
            '--run-id=' . $runId,
            '--no-interaction',
            '--no-ansi',
        ];

        // Detached, so neither the request timeout nor the end of the PHP-FPM request stops the job
        $commandLine = sprintf(
            'nohup %s > %s 2>&1 < /dev/null &',
            implode(' ', array_map('escapeshellarg', $command)),
            escapeshellarg($this->store->getPath($runId, 'out'))
        );

        $process = Process::fromShellCommandline($commandLine, $this->projectDir, $this->getEnv(), null, 30);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException(sprintf('Could not start the job: %s', trim($process->getErrorOutput())));
        }

        return $runId;
    }

    /**
     * @return array<string, string|false>
     */
    private function getEnv(): array
    {
        $env = [];

        foreach (array_keys($_SERVER + $_ENV + getenv()) as $key) {
            if (\is_string($key) && preg_match(self::REQUEST_ENV_PATTERN, $key)) {
                $env[$key] = false;
            }
        }

        return $this->binaries->getEnv() + $env;
    }
}
