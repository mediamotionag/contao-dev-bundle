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

class GeneratePageListener
{
    #[AsHook('generatePage', priority: 100)]
    public function onGeneratePage(PageModel $objCurrentPage, LayoutModel $objLayout, PageRegular $objPageRegular): void
    {
        $bolDevDomain = false;
        global $objPage;

        if($strDevDomains = \Contao\Config::get('dev_domains'))
        {
            $arrDevDomains = explode(',', $strDevDomains);

            if(is_array($arrDevDomains) && count($arrDevDomains) > 0)
            {
                $strCurrentDomain = $_SERVER['HTTP_HOST'];

                foreach($arrDevDomains as $strDevDomain)
                {
                    $strDevDomain = str_replace(array(' ', '*'), '', $strDevDomain);
                    $strDevDomain = urlencode($strDevDomain);

                    if($strDevDomain != '' && stristr($strCurrentDomain, $strDevDomain))
                    {
                        $bolDevDomain = true;
                    }
                }
            }

        }

        if($bolDevDomain === true)
        {
            $responseContext = \Contao\System::getContainer()->get('contao.routing.response_context_accessor')->getResponseContext();
            $htmlHeadBag = $responseContext->get(HtmlHeadBag::class);
            if ($responseContext && $responseContext->has(HtmlHeadBag::class)) {
                $htmlHeadBag->setMetaRobots('noindex,nofollow');
            }

            // Disable any Indexing development domains
            header("X-Robots-Tag: noindex, nofollow", true);

            // Disable Caching for development domains
            header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
            header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
            header("Cache-Control: post-check=0, pre-check=0", false);
            header("Pragma: no-cache");
        }
    }
}
