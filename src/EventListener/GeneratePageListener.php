<?php
/**
 * @copyright  Media Motion AG <https://www.mediamotion.ch>
 * @author     Rory Zünd (Media Motion AG)
 * @package    MemoDevBundle
 * @license    LGPL-3.0+
 * @see	       https://github.com/mediamotionag/contao-dev-bundle
 */

namespace Memo\DevBundle\EventListener;

use Contao\CoreBundle\Routing\ResponseContext\HtmlHeadBag\HtmlHeadBag;
use Contao\CoreBundle\Routing\ResponseContext\ResponseContextAccessor;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Memo\DevBundle\Service\DomainMatcher;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::RESPONSE, priority: 100)]
class GeneratePageListener
{
    public function __construct(
        private readonly ScopeMatcher $scopeMatcher,
        private readonly ResponseContextAccessor $responseContextAccessor,
    ) {
    }

    public function __invoke(ResponseEvent $event): void
    {
        $request = $event->getRequest();

        // Only process frontend requests
        if (!$this->scopeMatcher->isFrontendRequest($request)) {
            return;
        }

        // Only process main requests
        if (!$event->isMainRequest()) {
            return;
        }

        // Check the current domain against the dev_domains and local_domains
        $bolStageDomain = DomainMatcher::checkDomain('dev_domains');
        $bolLocalDomain = DomainMatcher::checkDomain('local_domains');

        $response = $event->getResponse();

        // Disable index (only for stage domains)
        if ($bolStageDomain === true) {
            // Set meta robots via response context if available
            $responseContext = $this->responseContextAccessor->getResponseContext();
            if ($responseContext && $responseContext->has(HtmlHeadBag::class)) {
                $htmlHeadBag = $responseContext->get(HtmlHeadBag::class);
                $htmlHeadBag->setMetaRobots('noindex,nofollow');
            }

            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        // Disable caching (for stage and local domains)
        if ($bolStageDomain === true || $bolLocalDomain === true) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
            $response->headers->set('Pragma', 'no-cache');
            $response->setPrivate();
        }
    }
}
