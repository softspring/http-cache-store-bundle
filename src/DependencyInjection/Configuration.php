<?php

namespace Softspring\Bundle\HttpCacheStoreBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('sfs_http_cache_store');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->scalarNode('adapter')
                    ->defaultValue('cache.app')
                ->end()
                ->scalarNode('logger')
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
