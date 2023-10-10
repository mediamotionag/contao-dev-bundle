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

    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../../config'));
        $loader->load('services.yaml');

        // Set default "Live" so the badge is always present in the template, to be replaced there
        $container->setParameter('contao.backend.badge_title', 'Live');
    }

}
