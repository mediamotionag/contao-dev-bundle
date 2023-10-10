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
use Contao\System;

#[AsHook('parseBackendTemplate')]
class ParseBackendTemplateListener
{
    public function __invoke(string $buffer, string $template): string
    {
        if ('be_main' === $template) {

            // Backend Title
            $strBackendTitle = Config::get('backend_title');
            if($strBackendTitle != ''){
                $buffer = preg_replace('/app-title(.*?)<\/span>/', 'app-title">Contao | '.$strBackendTitle.'</span>', $buffer);
            }

            // Default Badge
            $strBadge = "Live";
            $strClass = "badge-title--live";

            // Detect Local and set Badge
            $bolLocalDomain = self::checkDomain('local_domains');
            if($bolLocalDomain === true)
            {
                $strBadge = "Local";
                $strClass = "badge-title--local";
            }

            // Detect Stage and set Badge
            $bolStageDomain = self::checkDomain('dev_domains');
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

            if($strBackendTitle != ''){
                $buffer = preg_replace('/badge-title(.*?)<\/span>/', 'badge-title '.$strClass.'">'.$strBadge.'</span>', $buffer);
            }

        }

        return $buffer;
    }

    public static function checkDomain($strTyp='dev_domains')
    {
        // Check if the typ is valid
        if($strTyp != 'dev_domains' && $strTyp != 'local_domains')
        {
            return false;
        }

        // Get Config value
        $strDomains = Config::get($strTyp);

        // Get the matching domains and compare

        if($strDomains != '')
        {

            $arrDomains = explode(',', $strDomains);

            if(is_array($arrDomains) && count($arrDomains) > 0)
            {
                $strCurrentDomain = $_SERVER['HTTP_HOST'];

                foreach($arrDomains as $strDomain)
                {
                    $strDomain = str_replace(array(' ', '*'), '', $strDomain);
                    $strDomain = urlencode($strDomain);

                    if($strDomain != '' && stristr($strCurrentDomain, $strDomain))
                    {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}
