# HTTP Cache Store Bundle Features

Functional definition for `softspring/http-cache-store-bundle`.

This file defines the expected behavior and functional scope of the bundle.

## Purpose

- Replace Symfony HttpCache's default store with one backed by a PSR-6 cache pool.
- Let applications reuse Symfony Cache adapters for HttpCache metadata and bodies.
- Add optional logging for cache lookup and write activity.

## Main Features

- Provide a `CacheStore` implementation compatible with Symfony `StoreInterface`.
- Store cache metadata and cached response bodies in a configurable `CacheItemPoolInterface`.
- Match cached responses using the response `Vary` header.
- Purge both `http` and `https` variants of one URL key.
- Let applications configure the cache adapter service id.
- Let applications configure an optional logger service id.
- Replace the store used by the `http_cache` service through a compiler pass.

## Store Behavior Expectations

- Cached responses should be stored by normalized request URI key.
- Different `Vary` combinations should be stored under the same metadata key and resolved on lookup.
- Private headers configured by the store should be removed before persistence.
- Response bodies should be stored separately under a content digest key.
- Lookup should return `null` when no valid cache entry exists.

## Integration Expectations

- Enabling Symfony `framework.http_cache` should be enough for the bundle to plug into the `http_cache` service.
- The bundle should use `cache.app` by default unless another adapter is configured.
- Applications should be able to point the bundle to a custom cache pool service.
- Applications should be able to inject a Monolog channel or any PSR logger service for store logs.

## Current Limits

- The bundle only applies when the `http_cache` service exists.
- The store currently keeps metadata and bodies in the same configured cache backend, not in separate pools.
- The purge operation removes metadata keys, but cached body entries may remain until their TTL expires.
- Logging around fragment requests is helpful but based on URL parsing heuristics.
