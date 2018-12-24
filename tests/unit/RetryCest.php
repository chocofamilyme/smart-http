<?php
/**
 * @package Chocolife.me
 * @author  Kamet Aziza <kamet.a@chocolife.kz>
 */

namespace Unit;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Request;
use Chocofamily\SmartHttp\Client;
use function GuzzleHttp\Psr7\stream_for;
use Phalcon\Config;

class RetryCest
{

    public function tryToRepeatOnConnectException(\UnitTester $I)
    {
        $I->wantToTest('повторить при ошибке с соединением');

        $lastStatusCode = 200;
        $responses      = [
            new ConnectException('test', new Request('GET', 'test')),
            new Response($lastStatusCode),
        ];

        $c = $this->getPreparedClient($responses);
        $p = $c->sendAsync(new Request('GET', 'http://test.com'), []);

        $I->assertEquals($lastStatusCode, $p->wait()->getStatusCode());
    }

    public function tryToRepeatOnServerError(\UnitTester $I)
    {
        $I->wantToTest('повторить при ошибке сервера');

        $lastStatusCode = 200;
        $responses      = [
            new Response(500),
            new Response($lastStatusCode),
        ];

        $c = $this->getPreparedClient($responses);
        $p = $c->sendAsync(new Request('GET', 'http://test.com'), []);

        $I->assertEquals($lastStatusCode, $p->wait()->getStatusCode());
    }

    public function tryToRepeatUntilMaxRetries(\UnitTester $I)
    {
        $I->wantToTest('повторить до привышения попыток');

        $maxRetries = 3;
        $responses  = [];

        for ($i = 0; $i < $maxRetries + 1; $i++) {
            array_push($responses, new Response(500));
        }

        $c = $this->getPreparedClient($responses, [
            'maxRetries' => $maxRetries,
        ]);
        $p = $c->sendAsync(new Request('GET', 'http://test.com'), []);

        $I->expectException(ServerException::class, function () use ($p) {
            $p->wait();
        });
        $I->assertEquals($maxRetries, $c->repeater->getRetries());
    }

    public function tryToRepeatOnApiError(\UnitTester $I)
    {
        $I->wantToTest('повторить при ошибке с API');

        $lastStatusCode = 201;

        $stream = stream_for(json_encode([
            'status'     => 'error',
            'error_code' => 500,
            'message'    => 'test error',
        ]));

        $responses = [new Response(200, [], $stream), new Response($lastStatusCode)];

        $c = $this->getPreparedClient($responses);
        $p = $c->sendAsync(new Request('GET', 'http://test.com'), []);

        $I->assertEquals($lastStatusCode, $p->wait()->getStatusCode());
    }

    public function tryToSendTwoSequentialRequestsViaOneClient(\UnitTester $I)
    {
        $I->wantToTest('отправить два последовательных запроса с одного клиента');

        $maxRetries = 2;
        $responses  = [];

        for ($i = 0; $i < 2 * $maxRetries; $i++) {
            array_push($responses, new Response(500));
        }

        $c = $this->getPreparedClient($responses, [
            'maxRetries' => $maxRetries,
        ]);


        $I->expectException(ServerException::class, function () use ($c) {
            $c->send(new Request('GET', 'http://test.com'), []);
        });
        $I->expectException(ServerException::class, function () use ($c) {
            $c->send(new Request('GET', 'http://test.com'), []);
        });
        $I->assertEquals($maxRetries, $c->repeater->getRetries());
    }

    public function tryToDelay(\UnitTester $I)
    {
        $I->wantToTest('проверить задержку');

        $maxRetries = 3;
        $responses  = [];

        for ($i = 0; $i < $maxRetries; $i++) {
            array_push($responses, new Response(500));
        }

        $c = $this->getPreparedClient($responses, [
            'maxRetries' => $maxRetries,
        ]);
        $p = $c->sendAsync(new Request('GET', 'http://test.com'), []);

        $startTime = time();
        $I->expectException(ServerException::class, function () use ($p) {
            $p->wait();
        });
        $endTime = time();

        $I->assertEquals(5 * $c->repeater->getDelay() / 1000, $endTime - $startTime);
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
