<?php
/**
 * @package Chocolife.me
 * @author  Kamet Aziza <kamet.a@chocolife.kz>
 */

namespace Chocofamily\SmartHttp\Middleware;

use Chocofamily\SmartHttp\Http\Request;
use Chocofamily\SmartHttp\Http\Response;
use GuzzleHttp\Promise\Promise;
use function GuzzleHttp\Promise\promise_for;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Phalcon\Cache\BackendInterface;
use Psr\Http\Message\RequestInterface;

class CacheMiddleware
{
    const CACHE_KEY_PREFIX    = 'cache_';
    const SUCCESS_STATUS_CODE = 200;
    private $cache;

    public function __construct(BackendInterface $cache)
    {
        $this->cache = $cache;
    }

    public function __invoke($handler)
    {
        return function (RequestInterface $request, array $requestOptions) use ($handler) {
            $lifetime = $requestOptions[Request::CACHE_LIFETIME] ?? null;
            $key      = $request->getMethod() !== 'GET' ? null
                : $this->getKey($request->getMethod(), $request->getUri());

            if ($this->isCacheable($key, $lifetime)
                && !empty($data = $this->cache->get($key))) {
                return $this->getResponseFromCache($data);
            }

            /** @var Promise $promise */
            $promise = $handler($request, $requestOptions);

            return $promise->then(
                function ($value) use ($key, $lifetime) {
                    if ($this->isCacheable($key, $lifetime) && $this->isSuccessful($value)) {
                        $this->saveToCache($key, $value, $lifetime);
                    }

                    return \GuzzleHttp\Promise\promise_for($value);
                },
                function ($reason) {
                    return \GuzzleHttp\Promise\rejection_for($reason);
                }
            );
        };
    }

    private function getKey($method, $uri)
    {
        return self::CACHE_KEY_PREFIX.md5($method.$uri);
    }

    private function getResponseFromCache($data)
    {
        $response = new PsrResponse(200, $data['headers'], $data['body']);

        return promise_for($response);
    }

    private function isCacheable($key, $lifetime)
    {
        return !empty($key) && !empty($lifetime);
    }

    private function isSuccessful(PsrResponse $psrResponse)
    {
        $response = new Response($psrResponse);
        return $response->isSuccessful();
    }

    private function saveToCache($key, PsrResponse $response, $lifetime)
    {
        $data = [
            'headers' => $response->getHeaders(),
            'body'    => $response->getBody()->getContents(),
        ];
        $response->getBody()->rewind();
        $this->cache->save($key, $data, $lifetime);
    }
}
