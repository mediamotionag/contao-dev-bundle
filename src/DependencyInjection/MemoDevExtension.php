<?php

declare(strict_types=1);

namespace Memo\DevBundle\DependencyInjection;

use Contao\Config;
use Contao\System;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class MemoDevExtension extends Extension
{
    public static function checkDomain($strTyp='dev_domains', $arrConfig=array())
    {
        // Check if the typ is valid
        if($strTyp != 'dev_domains' && $strTyp != 'local_domains')
        {
            return false;
        }

        // Get the matching domains and compare
        if(array_key_exists($strTyp, $arrConfig) && $strDomains = $arrConfig[$strTyp])
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

    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../../config'));
        $loader->load('services.yaml');

        $this->setBadge($container);
    }

    private function setBadge($container)
    {
        // Load Contao Config
        $strConfigPath = '../system/config/localconfig.php';
        if(file_exists($strConfigPath)){
            include $strConfigPath;
        } else {
            return false;
        }

        // Default Badge
        $strBadge = "Live";

        // Detect Stage and set Badge
        $bolStageDomain = self::checkDomain('dev_domains', $GLOBALS['TL_CONFIG']);
        if($bolStageDomain === true)
        {
            $strBadge = "Stage";
        }

        // Detect Local and set Badge
        $bolLocalDomain = self::checkDomain('local_domains', $GLOBALS['TL_CONFIG']);
        if($bolLocalDomain === true)
        {
            $strBadge = "Local";
        }

        // Detect Content Freeze and set Badge
        if(array_key_exists('content_freeze', $GLOBALS['TL_CONFIG']) && $GLOBALS['TL_CONFIG']['content_freeze'] == true){
            $strBadge .= " + Content Freeze";
        }

        // Set Badge
        $container->setParameter('contao.backend.badge_title', $strBadge);

        // If backend title is set, use it
        if(array_key_exists('backend_title', $GLOBALS['TL_CONFIG']) && $GLOBALS['TL_CONFIG']['backend_title'] != ''){
            $container->setParameter('contao.backend.attributes', array('backend-title' => $GLOBALS['TL_CONFIG']['backend_title']));
        }

    }
}
