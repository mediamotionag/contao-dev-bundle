<?php
/**
 * @copyright  Media Motion AG <https://www.mediamotion.ch>
 * @author     Rory Zünd (Media Motion AG)
 * @package    MemoDevBundle
 * @license    LGPL-3.0+
 * @see           https://github.com/mediamotionag/contao-dev-bundle
 */

namespace Memo\DevBundle\EventListener;

use Contao\Config;
use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Memo\DevBundle\Service\DomainMatcher;

#[AsHook('parseBackendTemplate')]
class ParseBackendTemplateListener
{
    public function __invoke(string $buffer, string $template): string
    {
        if (in_array($template, ['be_main', 'be_login'])) {

            // Backend Title
            $strBackendTitle = Config::get('backend_title');
            if($strBackendTitle != ''){
                $buffer = preg_replace('/app-title(.*?)<\/span>/', 'app-title">Contao | '.$strBackendTitle.'</span>', $buffer);
            }

            // Default Badge
            $strBadge = "Live";
            $strClass = "badge-title--live";

            // Detect Local and set Badge
            $bolLocalDomain = DomainMatcher::checkDomain('local_domains');
            if($bolLocalDomain === true)
            {
                $strBadge = "Local";
                $strClass = "badge-title--local";
            }

            // Detect Stage and set Badge
            $bolStageDomain = DomainMatcher::checkDomain('dev_domains');
            if($bolStageDomain === true)
            {
                $strBadge = "Stage";
                $strClass = "badge-title--stage";
            }

            // Detect Content Freeze and set Badge
            $bolContentFreeze = Config::get('content_freeze');
            if($bolContentFreeze == true){
                $strBadge .= " + Content Freeze";
                $strClass .= " badge-title--freeze";
            }

            if($strClass != ''){
                $buffer = preg_replace('/badge-title(.*?)<\/span>/', 'badge-title '.$strClass.'">'.$strBadge.'</span>', $buffer);
            }

        }

        return $buffer;
    }
}
