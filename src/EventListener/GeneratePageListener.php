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
use Contao\PageRegular;
use Contao\LayoutModel;
use Contao\PageModel;
use Contao\System;
use Memo\DevBundle\Service\DomainMatcher;

class GeneratePageListener
{
    #[AsHook('generatePage', priority: 100)]
    public function onGeneratePage(PageModel $objCurrentPage, LayoutModel $objLayout, PageRegular $objPageRegular): void
    {

        // Check the current domain against the dev_domains and local_domains
        $bolStageDomain = DomainMatcher::checkDomain('dev_domains');
        $bolLocalDomain = DomainMatcher::checkDomain('local_domains');

        // Disable index (only for stage domains)
        if($bolStageDomain === true)
        {
            $responseContext = System::getContainer()->get('contao.routing.response_context_accessor')->getResponseContext();
            $htmlHeadBag = $responseContext->get(HtmlHeadBag::class);
            if ($responseContext && $responseContext->has(HtmlHeadBag::class)) {
                $htmlHeadBag->setMetaRobots('noindex,nofollow');
            }

            header("X-Robots-Tag: noindex, nofollow", true);
        }

        // Disable caching (for stage and local domains)
        if($bolStageDomain === true || $bolLocalDomain === true)
        {
            $GLOBALS['TL_CONFIG']['cacheMode'] = 'none';
            header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
            header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
            header("Cache-Control: post-check=0, pre-check=0", false);
            header("Pragma: no-cache");
        }
    }
}
