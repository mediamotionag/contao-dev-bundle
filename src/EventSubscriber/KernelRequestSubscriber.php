<?php

namespace Memo\DevBundle\EventSubscriber;

use Contao\BackendUser;
use Contao\Config;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class KernelRequestSubscriber implements EventSubscriberInterface
{
    protected $scopeMatcher;
    protected $tokenStorage;
    protected $router;
    protected $framework;

    public function __construct(
        ScopeMatcher $scopeMatcher,
        TokenStorageInterface $tokenStorage,
        RouterInterface $router,
        ContaoFramework $framework
    ) {
        $this->scopeMatcher = $scopeMatcher;
        $this->tokenStorage = $tokenStorage;
        $this->router = $router;
        $this->framework = $framework;
    }

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::REQUEST => [
                ['onKernelRequestContentFreeze', 5],
                ['onKernelRequestAssets', -10],
            ],
        ];
    }

    public function onKernelRequestContentFreeze(RequestEvent $e): void
    {
        $request = $e->getRequest();

        if (!$this->scopeMatcher->isBackendRequest($request)) {
            return;
        }

        if (!$this->isContentFreezeActive()) {
            return;
        }

        $routeName = $request->attributes->get('_route');

        if (in_array($routeName, ['contao_backend_logout', 'contao_backend_login', 'contao_backend_login_link'])) {
            return;
        }

        $token = $this->tokenStorage->getToken();
        $isAuthenticated = $token !== null 
            && $token->getUser() !== null 
            && is_object($token->getUser())
            && method_exists($token->getUser(), 'getUsername')
            && $token->getUser()->getUsername() !== '';

        if ($isAuthenticated) {
            $user = $token->getUser();
            
            if ($user instanceof BackendUser && $user->isAdmin) {
                return;
            }
            
            $logoutUrl = $this->router->generate('contao_backend_logout');
            $e->setResponse(new RedirectResponse($logoutUrl));
            return;
        }

        $loginUrl = $this->router->generate('contao_backend_login');
        $e->setResponse(new RedirectResponse($loginUrl));
    }

    public function onKernelRequestAssets(RequestEvent $e): void
    {
        $request = $e->getRequest();

        if (!$this->scopeMatcher->isBackendRequest($request)) {
            return;
        }

        if ($e->hasResponse()) {
            return;
        }

        $strRoot = getcwd();
        $assetsDir = '/bundles/memodev';
        $jsFile = $strRoot . $assetsDir . '/backend.js';
        $cssFile = $strRoot . $assetsDir . '/backend.css';
        
        if (file_exists($jsFile) && file_exists($cssFile)) {
            $jsTimestamp = filemtime($jsFile);
            $cssTimestamp = filemtime($cssFile);
            $GLOBALS['TL_JAVASCRIPT'][] = $assetsDir . '/backend.js|async|' . $jsTimestamp;
            $GLOBALS['TL_CSS'][] = $assetsDir . '/backend.css|static|' . $cssTimestamp;
        }
    }

    private function isContentFreezeActive(): bool
    {
        try {
            $this->framework->initialize();
            return (bool) Config::get('content_freeze');
        } catch (\Exception $e) {
            return false;
        }
    }
}
