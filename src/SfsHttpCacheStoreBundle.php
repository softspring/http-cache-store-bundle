<?php

namespace Softspring\Bundle\HttpCacheStoreBundle;

use Softspring\Bundle\HttpCacheStoreBundle\DependencyInjection\CompilerPass\ConfigureBundlePass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class SfsHttpCacheStoreBundle extends Bundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }

    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new ConfigureBundlePass());
    }
}
