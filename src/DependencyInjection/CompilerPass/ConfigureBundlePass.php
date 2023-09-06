<?php

namespace Softspring\Bundle\HttpCacheStoreBundle\DependencyInjection\CompilerPass;

use Softspring\Bundle\HttpCacheStoreBundle\HttpCache\CacheStore;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

class ConfigureBundlePass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('http_cache')) {
            return;
        }

        $cacheAdapter = $container->getDefinition($container->getParameter('sfs_http_cache_store.adapter'));
        $loggerServiceId = $container->getParameter('sfs_http_cache_store.logger');
        $logger = $loggerServiceId ? $container->getDefinition($loggerServiceId) : null;

        $cacheStore = new Definition(CacheStore::class);
        $cacheStore->setArgument('$cache', $cacheAdapter);
        $cacheStore->setArgument('$logger', $logger);
        $container->setDefinition(CacheStore::class, $cacheStore);

        $httpCacheDefinition = $container->getDefinition('http_cache');
        $httpCacheDefinition->replaceArgument(1, $cacheStore);
    }
}
