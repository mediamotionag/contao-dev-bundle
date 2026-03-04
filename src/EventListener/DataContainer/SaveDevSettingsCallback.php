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
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;

class SaveDevSettingsCallback
{
    /** @var RequestStack */
    private $requestStack;

    /** @var string */
    private $cacheDir;

    /** @var ContainerInterface */
    private $container;

    public function __construct(RequestStack $requestStack, $cacheDir, ContainerInterface $container)
    {
        $this->requestStack = $requestStack;
        $this->cacheDir = $cacheDir;
        $this->container = $container;
    }

    public function onSubmitCallback(?DataContainer $dc = null): void
    {
        // Clear cache
        $filesystem = new Filesystem();

        // Exclude the compiled container directory so the current request can finish
        $ref = new \ReflectionObject($this->container);
        $containerDir = basename(Path::getDirectory($ref->getFileName()));

        $finder = Finder::create()
            ->depth(0)
            ->exclude($containerDir)
            // Exclude contao directory to preserve language cache
            ->exclude('contao')
            ->in($this->cacheDir)
        ;

        foreach ($finder as $file) {
            $filesystem->remove($file->getPathname());
        }

        if (\function_exists('opcache_reset')) {
            opcache_reset();
        }

        // Show backend message
        Message::addInfo("Symfony Cache wurde geleert & Backend-Badge neu generiert.");
    }
}
