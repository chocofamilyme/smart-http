<?php

/**
 * @package Chocolife.me
 * @author  Kamet Aziza <kamet.a@chocolife.kz>
 */

namespace Chocofamily\SmartHttp\Middleware;

use Chocofamily\SmartHttp\Http\Request;
use Chocofamily\SmartHttp\Http\Response;
use GuzzleHttp\Promise\Create;
use Psr\SimpleCache\InvalidArgumentException;
use Throwable;
use function Chocofamily\SmartHttp\unparse_url;
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Psr\SimpleCache\CacheInterface;
use Psr\Http\Message\RequestInterface;

/**
 * Class CacheMiddleware
 *
 * @package Chocofamily\SmartHttp\Middleware
 */
class CacheMiddleware
{
    const DEFAULT_KEY_PREFIX  = 'smarthttp_';
    const SUCCESS_STATUS_CODE = 200;

    const EXCLUDED_PARAMS     = [
        'correlation_id',
        'span_id',
    ];

    /**
     * @var CacheInterface
     */
    private $cache;

    public function __construct(CacheInterface $cache)
    {
        $this->cache = $cache;
    }

    public function __invoke($handler)
    {
        return function (RequestInterface $request, array $requestOptions) use ($handler) {
            $lifetime = $requestOptions[Request::CACHE_LIFETIME] ?? null;
            $prefix   = $requestOptions[Request::CACHE_PREFIX] ?? '';
            $key      = $lifetime ? $this->getKey($request->getMethod(), $request->getUri(), $prefix) : '';

            try {
                if ($this->isCacheable($key, $lifetime) && !empty($data = $this->cache->get($key))) {
                    return $this->getResponseFromCache($data);
                }
            } catch (Throwable $e) {}

            /** @var Promise $promise */
            $promise = $handler($request, $requestOptions);

            return $promise->then(
                function ($value) use ($key, $lifetime) {
                    if ($this->isCacheable($key, $lifetime) && $this->isSuccessful($value)) {
                        $this->saveToCache($key, $value, $lifetime);
                    }

                    return Create::promiseFor($value);
                },
                function ($reason) {
                    return Create::rejectionFor($reason);
                }
            );
        };
    }

    private function getKey(string $method, string $uri, string $prefix = ''): string
    {
        if ($method !== 'GET') {
            return '';
        }

        if (empty($prefix)) {
            $prefix = self::DEFAULT_KEY_PREFIX;
        }

        $uri = $this->clearUrl($uri);

        return $prefix.md5($method.$uri);
    }

    /**
     * Очищает от лищних query параметоров
     *
     * @param string $url
     *
     * @return string
     */
    private function clearUrl(string $url): string
    {
        if (strpos($url, '?') === false) {
            return $url;
        }

        $parseUrl = parse_url($url);
        if (false == isset($parseUrl['query'])) {
            return $url;
        }

        if (strpos($parseUrl['query'], self::EXCLUDED_PARAMS[0]) === false) {
            return $url;
        }

        $parseUrl['query'] = htmlspecialchars_decode($parseUrl['query']);
        parse_str($parseUrl['query'], $parseQuery);

        foreach (self::EXCLUDED_PARAMS as $param) {
            if (isset($parseQuery[$param])) {
                unset($parseQuery[$param]);
            }
        }
        if ($parseQuery) {
            $parseUrl['query'] = http_build_query($parseQuery);
        } else {
            unset($parseUrl['query']);
        }

        return unparse_url($parseUrl);
    }

    private function getResponseFromCache($data)
    {
        $response = new PsrResponse(200, $data['headers'], $data['body']);

        return Create::promiseFor($response);
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

    /**
     * @param             $key
     * @param PsrResponse $response
     * @param             $lifetime
     *
     * @throws InvalidArgumentException
     */
    private function saveToCache($key, PsrResponse $response, $lifetime)
    {
        $data = [
            'headers' => $response->getHeaders(),
            'body'    => $response->getBody()->getContents(),
        ];
        $response->getBody()->rewind();
        $this->cache->set($key, $data, $lifetime);
    }
}
