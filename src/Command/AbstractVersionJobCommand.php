<?php
/**
 * @copyright  Media Motion AG <https://www.mediamotion.ch>
 * @author     Ali Gueler (Media Motion AG)
 * @package    MemoDevBundle
 * @license    LGPL-3.0+
 * @see        https://github.com/mediamotionag/contao-dev-bundle
 */

namespace Memo\DevBundle\Command;

use Memo\DevBundle\VersionSwitch\BinaryResolver;
use Memo\DevBundle\VersionSwitch\GitRepository;
use Memo\DevBundle\VersionSwitch\StatusStore;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Process\Exception\LogicException;
use Symfony\Component\Process\Exception\ProcessSignaledException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Exception\RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Run bookkeeping shared by the background jobs: one lock, a status JSON and a log per run.
 * Every step runs as its own process, so the jobs survive composer replacing vendor/ underneath them.
 */
abstract class AbstractVersionJobCommand extends Command
{
    protected string $runId;

    protected array $status = [];

    /** Set once the job changed code or cache, so the kernel this job runs on may be gone */
    protected bool $changed = false;

    private ?OutputInterface $echo = null;

    public function __construct(
        protected readonly string $projectDir,
        protected readonly GitRepository $git,
        protected readonly BinaryResolver $binaries,
        protected readonly StatusStore $store,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('run-id', null, InputOption::VALUE_REQUIRED, 'Run ID assigned by the backend');
    }

    /**
     * @return int|null an exit code to stop with, null to continue
     */
    protected function start(InputInterface $input, OutputInterface $output, array $status): ?int
    {
        $this->runId = (string) ($input->getOption('run-id') ?? $this->store->createRunId());

        if (!$this->store->isValidRunId($this->runId)) {
            $output->writeln('<error>Invalid run ID.</error>');

            return Command::FAILURE;
        }

        // Started by the backend, stdout goes to <id>.out; on the CLI, mirror the log to the terminal
        $this->echo = null === $input->getOption('run-id') ? $output : null;

        $this->status = ($this->store->read($this->runId) ?? []) + $status + [
            'runId' => $this->runId,
            'startedAt' => date(DATE_ATOM),
        ];

        // isLocked() in web requests holds the lock for a moment to probe it, so one miss is not a running job
        $locked = $this->store->lock();

        for ($attempt = 0; !$locked && $attempt < 10; ++$attempt) {
            usleep(100000);
            $locked = $this->store->lock();
        }

        if (!$locked) {
            return $this->finish(StatusStore::STATE_FAILED_UNCHANGED, 'Another job is already running.');
        }

        $this->status['pid'] = getmypid();

        return null;
    }

    /**
     * @return list<string>
     */
    protected function getComposerInstallCommand(): array
    {
        $command = [...$this->binaries->getComposerCommand(), 'install', '--no-interaction', '--no-progress', '--optimize-autoloader'];

        // Keep the mode of the current installation instead of guessing it from the environment
        $installed = @include $this->projectDir . '/vendor/composer/installed.php';

        if (\is_array($installed) && false === ($installed['root']['dev'] ?? true)) {
            $command[] = '--no-dev';
        }

        return $command;
    }

    /**
     * @return list<string>
     */
    protected function getConsoleCommand(): array
    {
        return [$this->binaries->getPhp(), $this->projectDir . '/vendor/bin/contao-console', '--no-interaction', '--no-ansi'];
    }

    /**
     * @param list<string> $args
     */
    protected function console(array $args): void
    {
        $this->exec([...$this->getConsoleCommand(), ...$args]);
    }

    /**
     * @param list<string> $command
     */
    protected function exec(array $command): void
    {
        $this->log('$ ' . implode(' ', array_map('basename', \array_slice($command, 0, 2))) . ' ' . implode(' ', \array_slice($command, 2)));

        $process = $this->createProcess($command);
        $process->run(function (string $type, string $buffer): void {
            $this->write($buffer);
        });

        if (!$process->isSuccessful()) {
            throw new \RuntimeException(sprintf('Command exited with code %d.', $process->getExitCode()));
        }
    }

    /**
     * @param list<string> $command
     */
    protected function createProcess(array $command): Process
    {
        return new Process($command, $this->projectDir, $this->binaries->getEnv(), null, null);
    }

    /**
     * Loads every class the remaining steps need while vendor/ still matches the running code.
     */
    protected function preload(): void
    {
        foreach ([IOException::class, LogicException::class, ProcessSignaledException::class, ProcessTimedOutException::class, RuntimeException::class] as $class) {
            class_exists($class);
        }

        $this->createProcess(['git', '--version'])->run(static function (): void {});
        $this->getComposerInstallCommand();
        $this->store->appendLog($this->runId, '');
        $this->store->write($this->runId, $this->status);
    }

    protected function setStep(string $step): void
    {
        $this->status['state'] = StatusStore::STATE_RUNNING;
        $this->status['step'] = $step;
        $this->status['stepTimes'][$step] = microtime(true);
        $this->store->write($this->runId, $this->status);
        $this->log(sprintf('[%s] %s', date('H:i:s'), strtoupper($step)));
    }

    protected function finish(string $state, ?string $message = null): int
    {
        $this->status['state'] = $state;
        $this->status['message'] = $message;
        $this->status['finishedAt'] = date(DATE_ATOM);
        $this->store->write($this->runId, $this->status);
        $this->log(sprintf('[%s] %s%s', date('H:i:s'), strtoupper($state), null !== $message ? ': ' . $message : ''));
        $this->store->prune();

        $exitCode = StatusStore::STATE_SUCCESS === $state ? Command::SUCCESS : Command::FAILURE;

        // The steps deleted the container this kernel runs on; its terminate listeners would fatal on lazy services
        if ($this->changed) {
            exit($exitCode);
        }

        return $exitCode;
    }

    protected function log(string $line): void
    {
        $this->write($line . "\n");
    }

    private function write(string $text): void
    {
        $this->store->appendLog($this->runId, $text);
        $this->echo?->write($text, false, OutputInterface::OUTPUT_RAW);
    }
}
