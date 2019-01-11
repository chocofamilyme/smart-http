<?php

namespace Chocofamily\SmartHttp\Http;

use Chocofamily\SmartHttp\CircuitBreaker;
use function GuzzleHttp\Promise\settle;
use Phalcon\Cache\BackendInterface;
use Phalcon\Config;
use Phalcon\Di\Injectable;

class Request extends Injectable
{
    const SUCCESS_STATE  = 'fulfilled';
    const SERVICE_NAME   = 'serviceName';
    const DATA           = 'data';
    const CACHE_LIFETIME = 'cache';

    /** @var \Chocofamily\SmartHttp\Client */
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
        Config $config,
        BackendInterface $cache
    ) {
        $this->httpClient = new \Chocofamily\SmartHttp\Client($config, $cache);
    }

    /**
     * @param string $method
     * @param string $uri
     *
     * @param array  $data
     *
     * @return mixed|\Psr\Http\Message\ResponseInterface
     * @throws \GuzzleHttp\Exception\GuzzleException
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
     * @return \GuzzleHttp\Promise\PromiseInterface
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
     * @throws \Throwable
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

        $options[CircuitBreaker::CB_TRANSFER_OPTION_KEY] = $data[self::SERVICE_NAME] ?? null;
        $options[$this->methods[$method]]                = $data[self::DATA] ?? null;
        $options[self::CACHE_LIFETIME]                   = $data[self::CACHE_LIFETIME] ?? null;

        return $options;
    }
}
