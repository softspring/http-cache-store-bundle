<?php

namespace Softspring\Bundle\HttpCacheStoreBundle\HttpCache;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpCache\StoreInterface;
use Symfony\Contracts\Cache\ItemInterface;

class CacheStore implements StoreInterface
{
    /** @psalm-var CacheItemPoolInterface $cache */
    protected CacheItemPoolInterface $cache;

    protected ?LoggerInterface $logger;

    /** @var \SplObjectStorage<Request, string> */
    private \SplObjectStorage $keyCache;

    private array $options;

    public function __construct(CacheItemPoolInterface $cache, LoggerInterface $logger = null, array $options = [])
    {
        $this->cache = $cache;
        $this->logger = $logger;
        $this->keyCache = new \SplObjectStorage();
        $this->options = array_merge([
            'private_headers' => ['Set-Cookie'],
        ], $options);
    }

    public function lookup(Request $request): ?Response
    {
        $key = $this->getCacheKey($request);

        if (!$entries = $this->getMetadata($key)) {
            return null;
        }

        // find a cached entry that matches the request.
        $match = null;
        foreach ($entries as $entry) {
            if ($this->requestsMatch(isset($entry[1]['vary'][0]) ? implode(', ', $entry[1]['vary']) : '', $request->headers->all(), $entry[0])) {
                $match = $entry;

                break;
            }
        }

        if (null === $match) {
            return null;
        }

        $logFragment = $this->processFragmentForLogger($request->getUri());
        $logVary = $this->processVaryForLogger($entry[1]['vary'] ?? [], $request);

        $headers = $match[1];
        if ($this->cache->hasItem($key = $headers['x-content-digest'][0])) {
            $this->logger && $this->logger->info(sprintf('HTTP CACHE HIT: "%s" %s %s', $request->getUri(), $logFragment, $logVary));

            return $this->restoreResponse($headers, $key);
        }

        $this->logger && $this->logger->info(sprintf('HTTP CACHE MISS: "%s" %s %s', $request->getUri(), $logFragment, $logVary));

        // TODO the metaStore referenced an entity that doesn't exist in
        // the entityStore. We definitely want to return nil but we should
        // also purge the entry from the meta-store when this is detected.
        return null;
    }

    protected function processVaryForLogger(array $vary, Request $request): string
    {
        if (!$this->logger) {
            return '';
        }

        if (!isset($vary[0])) {
            return '';
        }

        $headersVary = array_intersect_key($request->headers->all(), array_flip(array_map('strtolower', $vary)));
        $headersVary = array_map(fn ($k, $v) => "$k={$v[0]}", array_keys($headersVary), array_values($headersVary));

        return sprintf('(Vary: %s)', implode(', ', $headersVary));
    }

    protected function processFragmentForLogger(string $url): string
    {
        if (!$this->logger) {
            return '';
        }

        if (!str_contains($url, '/_fragment')) {
            return '';
        }

        $fragmentData = parse_url($url, PHP_URL_QUERY);
        $fragmentData = explode('&', $fragmentData);
        $fragmentData = array_filter($fragmentData, fn ($v) => str_starts_with($v, '_path'));
        $fragmentData = current($fragmentData);
        $fragmentData = explode('=', $fragmentData)[1];
        $fragmentData = urldecode($fragmentData);
        $fragmentData = explode('&', $fragmentData);
        $fragmentData = array_map(fn ($v) => explode('=', $v), $fragmentData);
        $fragmentData = array_combine(array_column($fragmentData, 0), array_column($fragmentData, 1));
        $controller = urldecode($fragmentData['_controller']);
        unset($fragmentData['_controller']);

        return sprintf('FRAGMENT %s(%s)', $controller, implode(', ', array_map(fn ($k, $v) => "$k={$v}", array_keys($fragmentData), array_values($fragmentData))));
    }

    public function write(Request $request, Response $response): string
    {
        $key = $this->getCacheKey($request);
        $storedEnv = $this->persistRequest($request);

        $digest = $this->generateContentDigest($response);
        $response->headers->set('X-Content-Digest', $digest);

        if (!$this->save($digest, $response->getContent(), false, $response->getTtl() + 1)) {
            throw new \RuntimeException('Unable to store the entity.');
        }

        if (!$response->headers->has('Transfer-Encoding')) {
            $response->headers->set('Content-Length', (string) \strlen($response->getContent()));
        }

        // read existing cache entries, remove non-varying, and add this one to the list
        $entries = [];
        $vary = $response->headers->get('vary');
        foreach ($this->getMetadata($key) as $entry) {
            if (!isset($entry[1]['vary'][0])) {
                $entry[1]['vary'] = [''];
            }

            if ($entry[1]['vary'][0] != $vary || !$this->requestsMatch($vary ?? '', $entry[0], $storedEnv)) {
                $entries[] = $entry;
            }
        }

        $headers = $this->persistResponse($response);
        unset($headers['age']);

        foreach ($this->options['private_headers'] as $h) {
            unset($headers[strtolower($h)]);
        }

        $this->logger && $this->logger->info(sprintf('HTTP CACHE WRITE: "%s" %s %s (TTL: %u)', $request->getUri(), $this->processVaryForLogger($headers['vary'] ?? [], $request), $this->processFragmentForLogger($request->getUri()), $response->getTtl()));

        array_unshift($entries, [$storedEnv, $headers]);

        if (!$this->save($key, serialize($entries), ttl: $response->getTtl())) {
            throw new \RuntimeException('Unable to store the metadata.');
        }

        return $key;
    }

    public function invalidate(Request $request): void
    {
        $modified = false;
        $key = $this->getCacheKey($request);

        $entries = [];
        foreach ($this->getMetadata($key) as $entry) {
            $response = $this->restoreResponse($entry[1]);
            if ($response->isFresh()) {
                $response->expire();
                $modified = true;
                $entries[] = [$entry[0], $this->persistResponse($response)];
            } else {
                $entries[] = $entry;
            }
        }

        if ($modified && !$this->save($key, serialize($entries))) {
            throw new \RuntimeException('Unable to store the metadata.');
        }
    }

    public function lock(Request $request): bool
    {
        return true;
    }

    public function unlock(Request $request): bool
    {
        return true;
    }

    public function isLocked(Request $request): bool
    {
        return false;
    }

    public function purge(string $url): bool
    {
        $http = preg_replace('#^https:#', 'http:', $url);
        $https = preg_replace('#^http:#', 'https:', $url);

        $purgedHttp = $this->doPurge($http);
        $purgedHttps = $this->doPurge($https);

        return $purgedHttp || $purgedHttps;
    }

    public function cleanup(): void
    {
        // nothing to do here
    }

    /**
     * Purges data for the given URL.
     */
    private function doPurge(string $url): bool
    {
        $key = $this->getCacheKey(Request::create($url));

        if ($this->cache->hasItem($key)) {
            $this->cache->deleteItem($key);

            return true;
        }

        return false;
    }

    /**
     * Loads data for the given key.
     */
    private function load(string $key): ?string
    {
        $item = $this->cache->getItem($key);

        return $item->isHit() ? $item->get() : null;
    }

    /**
     * Save data for the given key.
     */
    private function save(string $key, string $data, bool $overwrite = true, int $ttl = null): bool
    {
        /** @var ItemInterface $item */
        $item = $this->cache->getItem($key);

        if (!$overwrite && $item->isHit()) {
            return true;
        }

        $item->set($data);
        // $item->tag('http_cache');
        false !== $ttl && $item->expiresAfter($ttl);

        $this->cache->save($item);

        return true;
    }

    /**
     * Returns content digest for $response.
     */
    protected function generateContentDigest(Response $response): string
    {
        return 'en'.hash('sha256', $response->getContent());
    }

    /**
     * Determines whether two Request HTTP header sets are non-varying based on
     * the vary response header value provided.
     *
     * @param string|null $vary A Response vary header
     * @param array       $env1 A Request HTTP header array
     * @param array       $env2 A Request HTTP header array
     */
    private function requestsMatch(?string $vary, array $env1, array $env2): bool
    {
        if (empty($vary)) {
            return true;
        }

        foreach (preg_split('/[\s,]+/', $vary) as $header) {
            $key = str_replace('_', '-', strtolower($header));
            $v1 = $env1[$key] ?? null;
            $v2 = $env2[$key] ?? null;
            if ($v1 !== $v2) {
                return false;
            }
        }

        return true;
    }

    /**
     * Gets all data associated with the given key.
     *
     * Use this method only if you know what you are doing.
     */
    private function getMetadata(string $key): array
    {
        if (!$entries = $this->load($key)) {
            return [];
        }

        return unserialize($entries) ?: [];
    }

    /**
     * Generates a cache key for the given Request.
     *
     * This method should return a key that must only depend on a
     * normalized version of the request URI.
     *
     * If the same URI can have more than one representation, based on some
     * headers, use a Vary header to indicate them, and each representation will
     * be stored independently under the same cache key.
     */
    protected function generateCacheKey(Request $request): string
    {
        return 'md'.hash('sha256', $request->getUri());
    }

    /**
     * Returns a cache key for the given Request.
     */
    private function getCacheKey(Request $request): string
    {
        if (isset($this->keyCache[$request])) {
            return $this->keyCache[$request];
        }

        return $this->keyCache[$request] = $this->generateCacheKey($request);
    }

    /**
     * Persists the Request HTTP headers.
     */
    private function persistRequest(Request $request): array
    {
        return $request->headers->all();
    }

    /**
     * Persists the Response HTTP headers.
     */
    private function persistResponse(Response $response): array
    {
        $headers = $response->headers->all();
        $headers['X-Status'] = [$response->getStatusCode()];

        return $headers;
    }

    /**
     * Restores a Response from the HTTP headers and body.
     */
    private function restoreResponse(array $headers, string $key = null): Response
    {
        $status = $headers['X-Status'][0];
        unset($headers['X-Status']);

        $content = $this->cache->getItem($key);
        if ($content->isHit()) {
            $content = $content->get();
        } else {
            $content = '';
        }

        return new Response($content, $status, $headers);
    }
}
