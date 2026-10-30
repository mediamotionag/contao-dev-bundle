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
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'memo:dev:restore-backup', description: 'Restores a database backup created by a version switch and clears the cache.')]
class RestoreBackupCommand extends AbstractVersionJobCommand
{
    protected function configure(): void
    {
        parent::configure();

        $this->addArgument('backup', InputArgument::REQUIRED, 'The backup file name in var/backups/');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $backup = (string) $input->getArgument('backup');

        if (null !== ($exitCode = $this->start($input, $output, ['action' => 'restore', 'tag' => $backup, 'backup' => $backup]))) {
            return $exitCode;
        }

        try {
            $this->setStep('restore');
            $this->console(['contao:backup:restore', $backup]);

            $this->setStep('cache');
            $this->changed = true;
            $this->console(['cache:clear']);
        } catch (\Throwable $e) {
            $this->log('ERROR: ' . $e->getMessage());
            $this->status['failedStep'] = $this->status['step'];

            return $this->finish(StatusStore::STATE_FAILED, $e->getMessage());
        }

        return $this->finish(StatusStore::STATE_SUCCESS);
    }
}
