<?php

namespace Unit;

use Chocofamily\SmartHttp\Exception\CircuitIsClosedException;
use Chocofamily\SmartHttp\CircuitBreaker;
use Chocofamily\SmartHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use function GuzzleHttp\Psr7\stream_for;
use Helper\SmartHttp\CacheMock;
use Phalcon\Config;

/**
 * @package Chocolife.me
 * @author  Kamet Aziza <kamet.a@chocolife.kz>
 */
class CircuitBreakerCest
{
    public function tryToBlockServiceOnServerError(\UnitTester $I)
    {
        $I->wantToTest('заблокировать при ошибке сервера');

        $responses = [new Response('500')];

        $options = [
            CircuitBreaker::CB_TRANSFER_OPTION_KEY => 'test_service',
            'http_errors'          => true,
        ];

        $c = $this->getPreparedClient($responses);

        $I->expectException(ServerException::class, function () use ($c, $options) {
            $c->send(new Request('GET', 'http://test.com'), $options);
        });

        $I->expectException(CircuitIsClosedException::class, function () use ($c, $options) {
            $c->send(new Request('GET', 'http://test.com'), $options);
        });
    }

    public function tryToBlockServiceOnApiError(\UnitTester $I)
    {
        $I->wantToTest('заблокировать при ошибке соединении');
        $responses = [new Response(200, [], stream_for(json_encode([
            'status'     => 'error',
            'error_code' => 500,
            'message'    => 'test error',
        ])))];

        $options = [
            CircuitBreaker::CB_TRANSFER_OPTION_KEY => 'test_service',
            'http_errors'          => true,
        ];

        $c = $this->getPreparedClient($responses);

        $c->send(new Request('GET', 'http://test.com'), $options);

        $I->expectException(CircuitIsClosedException::class, function () use ($c, $options) {
            $c->send(new Request('GET', 'http://test.com'), $options);
        });
    }

    public function tryToBlockServiceOnConnectionError(\UnitTester $I)
    {
        $I->wantToTest('заблокировать при ошибке с API');

        $responses = [
            new ConnectException('test', new Request('get', 'test'))
        ];

        $options = [
            CircuitBreaker::CB_TRANSFER_OPTION_KEY => 'test_service',
            'http_errors'          => true,
        ];

        $c = $this->getPreparedClient($responses);

        $I->expectException(ConnectException::class, function () use ($c, $options) {
            $c->send(new Request('GET', 'http://test.com'), $options);
        });

        $I->expectException(CircuitIsClosedException::class, function () use ($c, $options) {
            $c->send(new Request('GET', 'http://test.com'), $options);
        });
    }


    public function tryToUnblockAfterExceededTimeout(\UnitTester $I)
    {
        $I->wantToTest('разблокировать после истечении времени');

        $responses = [new Response('500'), new Response('200')];

        $options = [
            CircuitBreaker::CB_TRANSFER_OPTION_KEY => 'test_service',
            'http_errors'          => true,
        ];

        $c = $this->getPreparedClient($responses, [
            'timeout' => 0.5
        ]);

        $I->expectException(ServerException::class, function () use ($c, $options) {
            $c->send(new Request('GET', 'http://test.com'), $options);
        });

        $I->expectException(CircuitIsClosedException::class, function () use ($c, $options) {
            $c->send(new Request('GET', 'http://test.com'), $options);
        });

        sleep(1);

        $responses = $c->send(new Request('GET', 'http://test.com'), $options);
        $I->assertEquals(200, $responses->getStatusCode());
    }

    private function getPreparedClient($responses, $params = [])
    {
        $handler = new MockHandler($responses);

        /** @var \Phalcon\Config $config */
        $config               = \Phalcon\Di::getDefault()->getShared('config')->get('smartHttp', []);
        $config['handler']    = $handler;
        $config['maxRetries'] = 1;
        $config['failures']   = 1;

        $config->merge(new Config($params));
        $cache = new CacheMock();

        return new Client($config, $cache);
    }
}
