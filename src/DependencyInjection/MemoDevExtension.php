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

        $container->setParameter('contao.backend.badge_title', 'Live');
    }

}
