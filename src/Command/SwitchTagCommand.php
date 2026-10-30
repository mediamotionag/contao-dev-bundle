<?php
/**
 * @copyright  Media Motion AG <https://www.mediamotion.ch>
 * @author     Ali Gueler (Media Motion AG)
 * @package    MemoDevBundle
 * @license    LGPL-3.0+
 * @see        https://github.com/mediamotionag/contao-dev-bundle
 */

namespace Memo\DevBundle\Command;

use Memo\DevBundle\VersionSwitch\StatusStore;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'memo:dev:switch-tag', description: 'Checks out a git tag (or rolls back the last switch), runs composer install, contao:migrate (with backup) and clears the cache.')]
class SwitchTagCommand extends AbstractVersionJobCommand
{
    protected function configure(): void
    {
        parent::configure();

        $this
            ->addArgument('tag', InputArgument::OPTIONAL, 'The git tag to switch to')
            ->addOption('rollback', null, InputOption::VALUE_NONE, 'Return to the ref the last switch started from')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $rollback = (bool) $input->getOption('rollback');
        $tag = (string) $input->getArgument('tag');

        if (!$rollback && '' === $tag) {
            $output->writeln('<error>Pass a tag or --rollback.</error>');

            return Command::FAILURE;
        }

        // Read before start(): the new run becomes the latest one
        $rollbackOf = $rollback ? $this->store->getRollbackTarget() : null;

        $initial = ['action' => $rollback ? 'rollback' : 'switch', 'tag' => $rollback ? ($rollbackOf['previousRef'] ?? '') : $tag];

        if (null !== ($exitCode = $this->start($input, $output, $initial))) {
            return $exitCode;
        }

        $this->setStep('preflight');

        try {
            if ($rollback) {
                if (null === $rollbackOf) {
                    throw new \RuntimeException('There is no switch to roll back.');
                }

                $target = $rollbackOf['previousRef'];
                $this->status['rollbackOf'] = $rollbackOf['runId'];

                if (!$this->git->refExists($target)) {
                    throw new \RuntimeException(sprintf('The ref "%s" does not exist.', $target));
                }
            } else {
                if (!$this->git->tagExists($tag)) {
                    throw new \InvalidArgumentException(sprintf('Tag "%s" does not exist.', $tag));
                }

                $target = 'refs/tags/' . $tag;
            }

            if ([] !== ($modified = $this->git->getModifiedFiles())) {
                throw new \RuntimeException("The checkout has modified tracked files:\n" . implode("\n", $modified));
            }

            $this->status['previousRef'] = $this->git->getRevertRef();
            $this->status['backup'] = null;
            $this->status['checkedOut'] = false;
            $composerInstall = $this->getComposerInstallCommand();
            $this->preload();
        } catch (\Throwable $e) {
            $this->log('ERROR: ' . $e->getMessage());
            $this->status['failedStep'] = 'preflight';

            return $this->finish(StatusStore::STATE_FAILED_UNCHANGED, $e->getMessage());
        }

        $this->log(sprintf('Switching from %s to %s', $this->status['previousRef'], $this->status['tag']));

        try {
            $this->setStep('checkout');
            $this->changed = true;
            $this->status['checkedOut'] = true;
            $this->git->checkout($target);

            $this->setStep('composer');
            $this->exec($composerInstall);

            $this->setStep('migrate');
            $this->migrate();

            $this->setStep('cache');
            // Without --no-warmup the new cache is built aside and swapped in, so running requests keep theirs longer
            $this->console(['cache:clear']);
            // No target argument: assets:install reads extra.public-dir from composer.json
            $this->console(['assets:install', '--symlink', '--relative']);

            return $this->finish(StatusStore::STATE_SUCCESS);
        } catch (\Throwable $e) {
            $this->log('ERROR: ' . $e->getMessage());
            $failedStep = $this->status['step'];
            $this->status['failedStep'] = $failedStep;
        }

        try {
            $this->setStep('revert');
            $this->git->checkout($this->status['previousRef']);
            $this->exec($composerInstall);
            $this->console(['cache:clear']);
        } catch (\Throwable $revertError) {
            $this->log('ERROR during revert: ' . $revertError->getMessage());

            return $this->finish(StatusStore::STATE_FAILED, sprintf('Step "%s" failed and the revert failed too: %s', $failedStep, $revertError->getMessage()));
        }

        if (null !== $this->status['backup']) {
            $this->log(sprintf('The database was NOT restored. Backup before the migration: %s', $this->status['backup']));
        }

        return $this->finish(StatusStore::STATE_FAILED_REVERTED, sprintf('Step "%s" failed: %s', $failedStep, $e->getMessage()));
    }

    /**
     * contao:migrate creates its own dump before it changes anything; it is identified by comparing the backup list.
     */
    private function migrate(): void
    {
        $before = $this->getBackupNames();

        try {
            $this->console(['contao:migrate']);
        } finally {
            try {
                $created = array_values(array_diff($this->getBackupNames(), $before));
                $this->status['backup'] = $created[0] ?? null;
                $this->log(null !== $this->status['backup'] ? 'Backup: ' . $this->status['backup'] : 'No backup created (nothing to migrate).');
            } catch (\Throwable $e) {
                $this->log('Could not determine the backup file: ' . $e->getMessage());
            }
        }
    }

    /**
     * @return list<string>
     */
    private function getBackupNames(): array
    {
        $process = $this->createProcess([...$this->getConsoleCommand(), 'contao:backup:list', '--format=json']);
        $process->mustRun();

        foreach (array_reverse(explode("\n", trim($process->getOutput()))) as $line) {
            $backups = json_decode($line, true);

            if (\is_array($backups)) {
                return array_column($backups, 'name');
            }
        }

        throw new \RuntimeException('Unexpected output of contao:backup:list.');
    }
}
