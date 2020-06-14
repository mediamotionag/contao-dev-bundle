<?php
/**
 * @copyright  Media Motion AG <https://www.mediamotion.ch>
 * @author     Rory Zünd (Media Motion AG)
 * @package    MemoDevBundle
 * @license    LGPL-3.0+
 * @see	       https://github.com/mediamotionag/contao-dev-bundle
 */
 
namespace Memo\DevBundle\EventListener;

use Contao\CoreBundle\ServiceAnnotation\Hook;
use Contao\PageRegular;
use Contao\LayoutModel;
use Contao\PageModel;
use Terminal42\ServiceAnnotationBundle\ServiceAnnotationInterface;

class HookListener implements ServiceAnnotationInterface
{
	/**
	 * @Hook("generatePage")
	 */
	public function onGeneratePage(PageModel $pageModel, LayoutModel $layout, PageRegular $pageRegular): void
	{
		$strDevDomains = \Contao\Config::get('dev_domains');
		die('<pre>'.print_r($pageModel, true) .'</pre>');
	}
}