<?php

namespace Softspring\Bundle\HttpCacheStoreBundle\Tests\Unit\HttpCache;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Softspring\Bundle\HttpCacheStoreBundle\HttpCache\CacheStore;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class CacheStoreTest extends TestCase
{
    public function testWriteAndLookupRoundTripCachedResponse(): void
    {
        $store = new CacheStore(new ArrayAdapter(), new NullLogger());
        $request = Request::create('https://example.com/products');
        $response = new Response('cached-body', 200, [
            'Vary' => 'Accept-Language',
            'Cache-Control' => 'public, max-age=60',
        ]);
        $response->setTtl(60);
        $request->headers->set('Accept-Language', 'en');

        $store->write($request, $response);
        $cached = $store->lookup($request);

        $this->assertInstanceOf(Response::class, $cached);
        $this->assertSame('cached-body', $cached->getContent());
        $this->assertSame(200, $cached->getStatusCode());
        $this->assertSame('en', $request->headers->get('Accept-Language'));
    }

    public function testLookupRespectsVaryHeader(): void
    {
        $store = new CacheStore(new ArrayAdapter(), new NullLogger());

        $englishRequest = Request::create('https://example.com/products');
        $englishRequest->headers->set('Accept-Language', 'en');
        $englishResponse = new Response('english', 200, [
            'Vary' => 'Accept-Language',
            'Cache-Control' => 'public, max-age=60',
        ]);
        $englishResponse->setTtl(60);

        $store->write($englishRequest, $englishResponse);

        $spanishRequest = Request::create('https://example.com/products');
        $spanishRequest->headers->set('Accept-Language', 'es');

        $this->assertNull($store->lookup($spanishRequest));
    }

    public function testPurgeMakesEntryUnavailable(): void
    {
        $store = new CacheStore(new ArrayAdapter(), new NullLogger());
        $request = Request::create('https://example.com/products');
        $response = new Response('cached-body', 200, [
            'Cache-Control' => 'public, max-age=60',
        ]);
        $response->setTtl(60);

        $store->write($request, $response);

        $this->assertTrue($store->purge('https://example.com/products'));
        $this->assertNull($store->lookup($request));
    }

    public function testPurgeRemovesBothHttpAndHttpsVariants(): void
    {
        $store = new CacheStore(new ArrayAdapter(), new NullLogger());
        $request = Request::create('http://example.com/products');
        $response = new Response('cached-body', 200, [
            'Cache-Control' => 'public, max-age=60',
        ]);
        $response->setTtl(60);

        $store->write($request, $response);

        $this->assertTrue($store->purge('https://example.com/products'));
        $this->assertNull($store->lookup($request));
    }

    public function testPurgeReturnsFalseWhenNothingWasRemoved(): void
    {
        $store = new CacheStore(new ArrayAdapter(), new NullLogger());

        $this->assertFalse($store->purge('https://example.com/missing'));
    }

    public function testInvalidateExpiresFreshStoredResponses(): void
    {
        $store = new CacheStore(new ArrayAdapter(), new NullLogger());
        $request = Request::create('https://example.com/products');
        $response = new Response('cached-body', 200, [
            'Cache-Control' => 'public, max-age=60',
        ]);
        $response->setTtl(60);

        $store->write($request, $response);
        $store->invalidate($request);

        $cached = $store->lookup($request);
        $this->assertInstanceOf(Response::class, $cached);
        $this->assertFalse($cached->isFresh());
    }

    public function testWritingSameVaryRequestReplacesPreviousEntry(): void
    {
        $store = new CacheStore(new ArrayAdapter(), new NullLogger());
        $request = Request::create('https://example.com/products');
        $request->headers->set('Accept-Language', 'en');

        $firstResponse = new Response('first', 200, [
            'Vary' => 'Accept-Language',
            'Cache-Control' => 'public, max-age=60',
        ]);
        $firstResponse->setTtl(60);
        $secondResponse = new Response('second', 200, [
            'Vary' => 'Accept-Language',
            'Cache-Control' => 'public, max-age=60',
        ]);
        $secondResponse->setTtl(60);

        $store->write($request, $firstResponse);
        $store->write($request, $secondResponse);

        $cached = $store->lookup($request);
        $this->assertInstanceOf(Response::class, $cached);
        $this->assertSame('second', $cached->getContent());
    }

    public function testPrivateHeadersAreRemovedBeforePersistence(): void
    {
        $store = new CacheStore(new ArrayAdapter(), new NullLogger());
        $request = Request::create('https://example.com/account');
        $response = new Response('private-body', 200, [
            'Cache-Control' => 'public, max-age=60',
            'Set-Cookie' => 'a=b',
        ]);
        $response->setTtl(60);

        $store->write($request, $response);
        $cached = $store->lookup($request);

        $this->assertInstanceOf(Response::class, $cached);
        $this->assertFalse($cached->headers->has('Set-Cookie'));
    }

    public function testCustomPrivateHeadersAreRemovedBeforePersistence(): void
    {
        $store = new CacheStore(new ArrayAdapter(), new NullLogger(), [
            'private_headers' => ['X-Private'],
        ]);
        $request = Request::create('https://example.com/account');
        $response = new Response('private-body', 200, [
            'Cache-Control' => 'public, max-age=60',
            'X-Private' => 'secret',
        ]);
        $response->setTtl(60);

        $store->write($request, $response);
        $cached = $store->lookup($request);

        $this->assertInstanceOf(Response::class, $cached);
        $this->assertFalse($cached->headers->has('X-Private'));
    }

    public function testLockMethodsAreNoOps(): void
    {
        $store = new CacheStore(new ArrayAdapter(), new NullLogger());
        $request = Request::create('https://example.com/products');

        $this->assertTrue($store->lock($request));
        $this->assertTrue($store->unlock($request));
        $this->assertFalse($store->isLocked($request));
        $store->cleanup();
    }
}
