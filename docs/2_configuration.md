# Basic configuration

First of all, you need to enable Symfony HttpCache feature:

```bash 

```yaml
# config/packages/framework.yaml
framework:
    http_cache:
        enabled: true
```

Also you need to configure the framework cache component, adapted to your needs:

```yaml
# config/packages/framework.yaml
framework:
    cache:
        app: 
            ...
```

At this point, the bundle is configured to use HttpCache store with framework cache component. 

By default the Store works with *cache.app* service, but you can change it in the configuration:

```yaml
# config/packages/sfs_http_cache_store.yaml
sfs_http_cache_store:
    adapter: 'cache.adapter.memcached'
```

# Configure custom adapter to use namespaces

You can configure a new cache pool to namespace the cache items:

```yaml
# config/packages/sfs_http_cache_store.yaml
services:
    http_client.cache.adapter.redis:
        parent: 'cache.adapter.redis'
        tags:
            - { name: 'cache.pool', namespace: 'http_cache' }

sfs_http_cache_store:
    adapter: 'htpp_client.cache.adapter.redis'
```

This configuration will prefix all cache items with *http_client:* namespace.

# Configure logger

You can configure a monolog logger to log cache hits and misses:

```yaml
sfs_http_cache_store:
    logger: 'monolog.logger.default'
```

Also you can configure a custom channel for the logger:

```yaml
monolog:
    channels: ['http_cache']

sfs_http_cache_store:
    logger: 'monolog.logger.http_cache'
```
