<?php

namespace Softspring\Bundle\HttpCacheStoreBundle\Tests\Unit\DependencyInjection\CompilerPass;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Softspring\Bundle\HttpCacheStoreBundle\DependencyInjection\CompilerPass\ConfigureBundlePass;
use Softspring\Bundle\HttpCacheStoreBundle\HttpCache\CacheStore;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class ConfigureBundlePassTest extends TestCase
{
    public function testProcessDoesNothingWithoutHttpCacheService(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('sfs_http_cache_store.adapter', 'cache.app');
        $container->setParameter('sfs_http_cache_store.logger', null);

        $pass = new ConfigureBundlePass();
        $pass->process($container);

        $this->assertFalse($container->hasDefinition(CacheStore::class));
    }

    public function testProcessConfiguresCacheStoreUsingServiceReferences(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('sfs_http_cache_store.adapter', 'cache.app');
        $container->setParameter('sfs_http_cache_store.logger', 'logger.http_cache');

        $container->setDefinition('app.cache_pool', new Definition(ArrayAdapter::class));
        $container->setAlias('cache.app', new Alias('app.cache_pool'));
        $container->setDefinition('logger.http_cache', new Definition(NullLogger::class));
        $container->setDefinition('http_cache', new Definition(DummyHttpCache::class, [null, null]));

        $pass = new ConfigureBundlePass();
        $pass->process($container);

        $cacheStoreDefinition = $container->getDefinition(CacheStore::class);

        $this->assertEquals(new Reference('cache.app'), $cacheStoreDefinition->getArgument('$cache'));
        $this->assertEquals(new Reference('logger.http_cache'), $cacheStoreDefinition->getArgument('$logger'));
        $this->assertEquals(new Reference(CacheStore::class), $container->getDefinition('http_cache')->getArgument(1));
    }
}

class DummyHttpCache
{
    public function __construct(public mixed $arg0, public mixed $store)
    {
    }
}
