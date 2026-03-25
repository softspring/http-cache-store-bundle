<?php

namespace Softspring\Bundle\HttpCacheStoreBundle\DependencyInjection\CompilerPass;

use Softspring\Bundle\HttpCacheStoreBundle\HttpCache\CacheStore;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class ConfigureBundlePass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('http_cache')) {
            return;
        }

        $cacheAdapter = (string) $container->getParameter('sfs_http_cache_store.adapter');
        $loggerServiceId = $container->getParameter('sfs_http_cache_store.logger');

        $cacheStore = new Definition(CacheStore::class);
        $cacheStore->setArgument('$cache', new Reference($cacheAdapter));
        $cacheStore->setArgument('$logger', $loggerServiceId ? new Reference((string) $loggerServiceId) : null);
        $container->setDefinition(CacheStore::class, $cacheStore);

        $httpCacheDefinition = $container->getDefinition('http_cache');
        $httpCacheDefinition->replaceArgument(1, new Reference(CacheStore::class));
    }
}
