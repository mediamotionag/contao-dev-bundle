<?php
/**
 * @copyright  Media Motion AG <https://www.mediamotion.ch>
 * @author     Rory Zünd (Media Motion AG)
 * @package    MemoDevBundle
 * @license    LGPL-3.0+
 * @see	       https://github.com/mediamotionag/contao-dev-bundle
 */

namespace Memo\DevBundle\EventListener\DataContainer;

use Contao\DataContainer;
use Contao\Message;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

class SaveDevSettingsCallback
{
    public function __construct(
        private readonly string $cacheDir,
    ) {
    }

    public function onSubmitCallback(?DataContainer $dc = null): void
    {
        $filesystem = new Filesystem();

        // Clear all cache contents except the compiled container directory
        $finder = Finder::create()
            ->depth(0)
            ->in($this->cacheDir)
        ;

        foreach ($finder as $file) {
            // Skip compiled container directories (e.g. ContainerAbcDef/)
            if ($file->isDir() && str_starts_with($file->getFilename(), 'Container')) {
                continue;
            }
            $filesystem->remove($file->getPathname());
        }

        if (\function_exists('opcache_reset')) {
            opcache_reset();
        }

        // Show backend message
        Message::addInfo("Symfony Cache wurde geleert & Backend-Badge neu generiert.");
    }
}
