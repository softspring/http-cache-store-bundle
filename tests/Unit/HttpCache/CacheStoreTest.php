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
}
