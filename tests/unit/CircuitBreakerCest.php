<?php

namespace Unit;

use Chocofamily\SmartHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Phalcon\Config;

/**
 * @package Chocolife.me
 * @author  Kamet Aziza <kamet.a@chocolife.kz>
 */

class CircuitBreakerCest
{
    public function tryToThrowException(\UnitTester $I)
    {
        $I->wantToTest('');

        $lastStatusCode = 200;
        $responses      = [
            new Response('404'),
        ];

        $c = $this->getPreparedClient($responses);
        $p = $c->send(
            new Request('GET', 'http://test.com'),
            [
                'my_custom_option_key' => 'test_service',
                'http_errors' => true
            ]
        );
    }

    private function getPreparedClient($responses, $params = [])
    {
        $handler = new MockHandler($responses);

        /** @var \Phalcon\Config $config */
        $config               = \Phalcon\Di::getDefault()->getShared('config')->get('smartHttp', []);
        $config['handler']    = $handler;
        $config['maxRetries'] = 3;

        $config->merge(new Config($params));

        return new Client($config);
    }
}
