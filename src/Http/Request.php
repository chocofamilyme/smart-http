<?php

namespace Chocofamily\SmartHttp\Http;

use Chocofamily\SmartHttp\CircuitBreaker;
use Chocofamily\SmartHttp\Client;
use GuzzleHttp\Promise\PromiseInterface;
use function GuzzleHttp\Promise\settle;
use Psr\Http\Message\ResponseInterface;
use Throwable;
use Psr\SimpleCache\CacheInterface;

class Request
{
    const SUCCESS_STATE  = 'fulfilled';
    const SERVICE_NAME   = 'serviceName';
    const DATA           = 'data';
    const CACHE_LIFETIME = 'cache';
    const CACHE_PREFIX   = 'cachePrefix';

    const DATA_KEY = ['body', 'form_params', 'multipart', 'query', 'json'];

    /** @var Client */
    private $httpClient;

    /**
     * Доступные методы для отправки запроса
     * Значение означает каким параметром отправлять
     *
     * @var array
     */
    private $methods = [
        'GET'     => 'query',
        'HEAD'    => 'query',
        'POST'    => 'form_params',
        'PUT'     => 'form_params',
        'PATCH'   => 'form_params',
        'DELETE'  => 'query',
        'OPTIONS' => 'query',
    ];

    public function __construct(
        array $config,
        CacheInterface $cache
    ) {
        $this->httpClient = new Client($config, $cache);
    }

    /**
     * @param string $method
     * @param string $uri
     *
     * @param array  $data
     *
     * @return ResponseInterface
     */
    public function send(string $method, string $uri, $data = [])
    {
        $method  = strtoupper($method);
        $options = array_merge($data, $this->generateOptions($method, $data));

        return $this->httpClient->request($method, $uri, $options);
    }

    /**
     * @param string $method
     * @param string $uri
     *
     * @param array  $data
     *
     * @return PromiseInterface
     */
    public function sendAsync(string $method, string $uri, $data = [])
    {
        $method  = strtoupper($method);
        $options = array_merge($data, $this->generateOptions($method, $data));

        return $this->httpClient->requestAsync($method, $uri, $options);
    }

    /**
     * @param      $requests array
     *
     * @return array
     * @throws Throwable
     */
    public function sendMultiple($requests)
    {
        $promises = [];

        foreach ($requests as $name => $data) {
            $data['method']  = strtoupper($data['method']);
            $options         = array_merge($data, $this->generateOptions($data['method'], $data));
            $promises[$name] = $this->httpClient->requestAsync($data['method'], $data['path'], $options);
        }

        return settle($promises)->wait();
    }

    private function generateOptions($method, $data)
    {
        $options = [];

        if ($this->isGet($method) || $this->doesntHaveData($data)) {
            $options[$this->methods[$method]] = $data[self::DATA] ?? null;
        }

        $options[CircuitBreaker::CB_TRANSFER_OPTION_KEY] = $data[self::SERVICE_NAME] ?? null;
        $options[self::CACHE_LIFETIME]                   = $data[self::CACHE_LIFETIME] ?? null;

        return $options;
    }

    /**
     * @param array $data
     *
     * @return bool
     */
    private function doesntHaveData(array $data): bool
    {
        if (!empty($data[self::DATA]) && $this->hasAnotherData($data)) {
            return false;
        }

        return true;
    }

    /**
     * @param array $data
     *
     * @return bool
     */
    private function hasAnotherData(array $data): bool
    {
        foreach (self::DATA_KEY as $key) {
            if (array_key_exists($key, $data)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $method
     *
     * @return bool
     */
    private function isGet(string $method): bool
    {
        return strtoupper($method) == 'GET';
    }

    /**
     * @return Client
     */
    public function getHttpClient(): Client
    {
        return $this->httpClient;
    }

    /**
     * @param Client $httpClient
     */
    public function setHttpClient(Client $httpClient)
    {
        $this->httpClient = $httpClient;
    }
}
