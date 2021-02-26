<?php
/**
 * @copyright  Media Motion AG <https://www.mediamotion.ch>
 * @author     Rory Zünd (Media Motion AG)
 * @package    MemoDevBundle
 * @license    LGPL-3.0+
 * @see	       https://github.com/mediamotionag/contao-dev-bundle
 */

namespace Memo\DevBundle\Service;

use Contao\Controller;
use Psr\Log\LogLevel;
use Contao\CoreBundle\Monolog\ContaoContext;


class HookListener
{

	public function setDevSettings( $objPage, $objLayout, $objPageRegular )
	{
		$bolDevDomain = false;

		if($strDevDomains = \Contao\Config::get('dev_domains'))
		{

			$arrDevDomains = explode(',', $strDevDomains);

			if(is_array($arrDevDomains) && count($arrDevDomains) > 0)
			{
				$strCurrentDomain = $_SERVER['HTTP_HOST'];

				foreach($arrDevDomains as $strDevDomain)
				{
					$strDevDomain = str_replace(' ', '', $strDevDomain);
					$strDevDomain = str_replace('*', '', $strDevDomain);
					$strDevDomain = urlencode($strDevDomain);

					if($strDevDomain != '' && stristr($strCurrentDomain, $strDevDomain))
					{
						$bolDevDomain = true;
					}
				}
			}

		}

		if($bolDevDomain)
		{
			// Disable any Indexing development domains
			$objPage->robots = 'noindex,nofollow';
			$objPage->robotsTxt = 'Disallow:memocustom';
			header("X-Robots-Tag: noindex, nofollow", true);

			// Disable Caching for development domains
			header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
			header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
			header("Cache-Control: post-check=0, pre-check=0", false);
			header("Pragma: no-cache");
		}
	}

}
