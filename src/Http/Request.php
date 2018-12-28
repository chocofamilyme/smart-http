<?php

namespace Chocofamily\SmartHttp\Http;

use Chocofamily\SmartHttp\CircuitBreaker;
use function GuzzleHttp\Promise\settle;
use Phalcon\Cache\BackendInterface;
use Phalcon\Config;
use Phalcon\Di\Injectable;

class Request extends Injectable
{
    const SUCCESS_STATE = 'fulfilled';
    const HTTP_METHODS         = [
        'GET'     => 1,
        'POST'    => 2,
        'PUT'     => 3,
        'DELETE'  => 4,
        'OPTIONS' => 5,
    ];

    /** @var \Chocofamily\SmartHttp\Client */
    private $httpClient;

    /** @var string */
    private $serviceName;

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
        $this->httpClient  = new \Chocofamily\SmartHttp\Client($config, $cache);
        $this->serviceName = $config->get('serviceName');
    }

    /**
     * @param string $method
     * @param string $uri
     *
     * @param array  $data
     *
     * @param null   $serviceName
     *
     * @return mixed|\Psr\Http\Message\ResponseInterface
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function send(string $method, string $uri, $data = [], $serviceName = null)
    {
        $options                          = $this->generateOptions($serviceName);
        $options[$this->methods[$method]] = $data;

        return $this->httpClient->request($method, $uri, $options);
    }

    /**
     * @param string $method
     * @param string $uri
     *
     * @param array  $data
     * @param null   $serviceName
     *
     * @return \GuzzleHttp\Promise\PromiseInterface
     */
    public function sendAsync(string $method, string $uri, $data = [], $serviceName = null)
    {
        $options                          = $this->generateOptions($serviceName);
        $options[$this->methods[$method]] = $data;

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

        foreach ($requests as $name => $request) {
            $options = [];

            $options[$this->methods[$request['method']]] = $request['data'] ?? null;
            $options[CircuitBreaker::CB_TRANSFER_OPTION_KEY] = $request['serviceName'] ?? null;

            $promises[$name] = $this->httpClient->requestAsync($request['method'], $request['path'], $options);
        }

        return settle($promises)->wait();
    }

    /**
     * @return string
     */
    public function getServiceName()
    {
        return $this->serviceName;
    }

    private function generateOptions($serviceName)
    {
        $options = [];

        if (isset($serviceName) || isset($this->serviceName)) {
            $options[CircuitBreaker::CB_TRANSFER_OPTION_KEY] = $serviceName ?: $this->serviceName;
        }

        return $options;
    }
}
