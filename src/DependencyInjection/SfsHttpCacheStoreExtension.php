<?php

namespace Softspring\Bundle\HttpCacheStoreBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;

class SfsHttpCacheStoreExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $processor = new Processor();
        $configuration = new Configuration();
        $config = $processor->processConfiguration($configuration, $configs);

        $container->setParameter('sfs_http_cache_store.adapter', $config['adapter']);
        $container->setParameter('sfs_http_cache_store.logger', $config['logger'] ?? null);
    }
}
