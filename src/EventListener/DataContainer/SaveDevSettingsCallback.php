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
use Contao\System;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;

class SaveDevSettingsCallback
{
    private $requestStack;

    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }

    public function onSubmitCallback(DataContainer $dc = null): void
    {
        // Clear cache
        $filesystem = new Filesystem();

        // Get Container Parameter
        $container = System::getContainer();
        $cacheDir = $container->getParameter('kernel.cache_dir');
        $ref = new \ReflectionObject($container);
        $containerDir = basename(Path::getDirectory($ref->getFileName()));

        $finder = Finder::create()
            ->depth(0)
            ->exclude($containerDir)
            ->in($cacheDir)
        ;

        foreach ($finder as $file) {
            $filesystem->remove($file->getPathname());
        }

        if (\function_exists('opcache_reset')) {
            opcache_reset();
        }

        if (\function_exists('apc_clear_cache') && !\ini_get('apc.stat')) {
            apc_clear_cache();
        }

        // Show backend message
        Message::addInfo("Symfony Cache wurde geleert & Backend-Badge neu generiert.");
    }
}
