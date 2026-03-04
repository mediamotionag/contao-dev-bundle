<?php
/**
 * @copyright  Media Motion AG <https://www.mediamotion.ch>
 * @author     Rory Zünd (Media Motion AG)
 * @package    MemoDevBundle
 * @license    LGPL-3.0+
 * @see	       https://github.com/mediamotionag/contao-dev-bundle
 */

namespace Memo\DevBundle\EventListener;

use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\CoreBundle\Routing\ResponseContext\HtmlHeadBag\HtmlHeadBag;
use Contao\CoreBundle\Routing\ResponseContext\ResponseContextAccessor;
use Contao\LayoutModel;
use Contao\PageModel;
use Contao\PageRegular;
use Memo\DevBundle\Service\DomainMatcher;

class GeneratePageListener
{
    public function __construct(
        private readonly ResponseContextAccessor $responseContextAccessor,
        private readonly DomainMatcher $domainMatcher,
    ) {
    }

    #[AsHook('generatePage', priority: 100)]
    public function onGeneratePage(PageModel $objCurrentPage, LayoutModel $objLayout, PageRegular $objPageRegular): void
    {

        // Check the current domain against the dev_domains and local_domains
        $bolStageDomain = $this->domainMatcher->checkDomain('dev_domains');
        $bolLocalDomain = $this->domainMatcher->checkDomain('local_domains');

        // Disable index (only for stage domains)
        if($bolStageDomain === true)
        {
            $responseContext = $this->responseContextAccessor->getResponseContext();
            if ($responseContext !== null && $responseContext->has(HtmlHeadBag::class)) {
                $htmlHeadBag = $responseContext->get(HtmlHeadBag::class);
                $htmlHeadBag->setMetaRobots('noindex,nofollow');
            }

            header("X-Robots-Tag: noindex, nofollow", true);
        }

        // Disable caching (for stage and local domains)
        if($bolStageDomain === true || $bolLocalDomain === true)
        {
            header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
            header("Pragma: no-cache");
        }
    }
}
