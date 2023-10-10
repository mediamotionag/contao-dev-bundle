<?php

namespace Memo\DevBundle\EventSubscriber;

use Contao\CoreBundle\Routing\ScopeMatcher;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class KernelRequestSubscriber implements EventSubscriberInterface
{
    protected $scopeMatcher;

    public function __construct(ScopeMatcher $scopeMatcher)
    {
        $this->scopeMatcher = $scopeMatcher;
    }

    public static function getSubscribedEvents()
    {
        return [KernelEvents::REQUEST => 'onKernelRequest'];
    }

    public function onKernelRequest(RequestEvent $e): void
    {
        $request = $e->getRequest();

        if ($this->scopeMatcher->isBackendRequest($request)) {

            // Filepath
            $strRoot = getcwd();
            $assetsDir = '/bundles/memodev';
            $jsTimestamp = filemtime($strRoot . $assetsDir . '/backend.js');
            $cssTimestamp = filemtime($strRoot . $assetsDir . '/backend.css');
            $GLOBALS['TL_JAVASCRIPT'][] = $assetsDir . '/backend.js|async|' . $jsTimestamp;
            $GLOBALS['TL_CSS'][] = $assetsDir . '/backend.css|static|' . $cssTimestamp;
        }
    }
}
