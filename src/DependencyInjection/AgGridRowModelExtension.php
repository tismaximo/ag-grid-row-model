<?php

namespace AgGridRowModel\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\Config\FileLocator;

class AgGridRowModelExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configLoader = new YamlFileLoader(
            $container,
            new FileLocator(__DIR__.'/../Resources/config')
        );
        $configLoader->load('services.yaml');
    }
}