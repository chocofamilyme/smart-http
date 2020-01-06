<?php

namespace Unit;

use Chocofamily\SmartHttp\Exception\CircuitIsClosedException;
use Chocofamily\SmartHttp\CircuitBreaker;
use Chocofamily\SmartHttp\Client;
use Codeception\Example;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Helper\SmartHttp\CacheMock;
use UnitTester;

/**
 * @package Chocolife.me
 * @author  Kamet Aziza <kamet.a@chocolife.kz>
 */
class CircuitBreakerCest
{
    /**
     * @dataprovider exceptionDataProvider
     *
     * @param UnitTester          $I
     * @param Example $data
     *
     * @throws GuzzleException
     */
    protected function tryToBlockServiceOnException(UnitTester $I, Example $data)
    {
        $I->wantToTest($data['message']);

        $options = [
            CircuitBreaker::CB_TRANSFER_OPTION_KEY => 'test_service',
            'http_errors'                          => true,
        ];

        $c = $this->getPreparedClient($data['responses']);

        if (isset($data['exception'])) {
            $I->expectThrowable($data['exception'], function () use ($c, $options) {
                $c->send(new Request('GET', 'http://test.com'), $options);
            });
        } else {
            $c->send(new Request('GET', 'http://test.com'), $options);
        }

        $I->expectThrowable(CircuitIsClosedException::class, function () use ($c, $options) {
            $c->send(new Request('GET', 'http://test.com'), $options);
        });
    }

    protected function exceptionDataProvider()
    {
        return [
            [
                'message'   => 'заблокировать при ошибке сервера',
                'responses' => [new Response('500')],
                'exception' => ServerException::class,
            ],
            [
                'message'   => 'заблокировать при ошибке соединении',
                'responses' => [new ConnectException('test', new Request('get', 'test'))],
                'exception' => ConnectException::class,
            ],
        ];
    }

    /**
     * @param UnitTester $I
     */
    public function tryToUnblockAfterExceededTimeout(UnitTester $I)
    {
        $I->wantToTest('разблокировать после истечении времени');

        $responses = [new Response('500'), new Response('200')];

        $options = [
            CircuitBreaker::CB_TRANSFER_OPTION_KEY => 'test_service',
            'http_errors'                          => true,
        ];

        $c = $this->getPreparedClient($responses, [
            'retry_timout' => 0.5,
        ]);

        $I->expectThrowable(ServerException::class, function () use ($c, $options) {
            $c->send(new Request('GET', 'http://test.com'), $options);
        });

        $I->expectThrowable(CircuitIsClosedException::class, function () use ($c, $options) {
            $c->send(new Request('GET', 'http://test.com'), $options);
        });

        sleep(1);

        $responses = $c->send(new Request('GET', 'http://test.com'), $options);
        $I->assertEquals(200, $responses->getStatusCode());
    }

    /**
     * @param       $responses
     * @param array $params
     *
     * @return Client
     */
    private function getPreparedClient($responses, $params = [])
    {
        $handler = new MockHandler($responses);

        /** @var array $config */
        $config               = [];
        $config['handler']    = $handler;
        $config['maxRetries'] = 1;
        $config['failures']   = 1;

        $config      = array_merge($config, $params);
        $cache = new CacheMock();

        return new Client($config, $cache);
    }
}
