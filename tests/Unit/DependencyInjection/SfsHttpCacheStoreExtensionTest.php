<?php

namespace Softspring\Bundle\HttpCacheStoreBundle\Tests\Unit\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Softspring\Bundle\HttpCacheStoreBundle\DependencyInjection\SfsHttpCacheStoreExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class SfsHttpCacheStoreExtensionTest extends TestCase
{
    public function testLoadSetsDefaultParameters(): void
    {
        $container = new ContainerBuilder();
        $extension = new SfsHttpCacheStoreExtension();

        $extension->load([], $container);

        $this->assertSame('cache.app', $container->getParameter('sfs_http_cache_store.adapter'));
        $this->assertNull($container->getParameter('sfs_http_cache_store.logger'));
    }

    public function testLoadSetsCustomParameters(): void
    {
        $container = new ContainerBuilder();
        $extension = new SfsHttpCacheStoreExtension();

        $extension->load([[
            'adapter' => 'app.http_cache_pool',
            'logger' => 'monolog.logger.http_cache',
        ]], $container);

        $this->assertSame('app.http_cache_pool', $container->getParameter('sfs_http_cache_store.adapter'));
        $this->assertSame('monolog.logger.http_cache', $container->getParameter('sfs_http_cache_store.logger'));
    }
}
