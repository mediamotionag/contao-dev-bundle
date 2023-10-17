<?php
/**
 * @copyright  Media Motion AG <https://www.mediamotion.ch>
 * @author     Rory Zünd (Media Motion AG)
 * @package    MemoDevBundle
 * @license    LGPL-3.0+
 * @see	       https://github.com/mediamotionag/contao-dev-bundle
 */

namespace Memo\DevBundle\Service;

use Contao\Config;

class DomainMatcher {

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
