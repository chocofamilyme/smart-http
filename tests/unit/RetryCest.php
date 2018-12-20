<?php
/**
 * @package Chocolife.me
 * @author  Kamet Aziza <kamet.a@chocolife.kz>
 */

namespace Unit;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Request;
use Chocofamily\SmartHttp\Client;

class RetryCest
{
    public function tryToRetriesWhenDeciderReturnsTrue(\UnitTester $I)
    {
        $I->wantToTest('разрешить повторить запрос');


        $handler = new MockHandler([new Response(200), new Response(201), new Response(202)]);


        $config = \Phalcon\Di::getDefault()->getShared('config')->get('smartHttp', []);
        $config['handler'] = $handler;

        $c = new Client($config);
        $p = $c->sendAsync(new Request('GET', 'http://test.com'), []);
        $p->wait();

        //$I->assertEquals(3, $calls);
        //$I->assertEquals(2, $delayCalls);
        $I->assertEquals(202, $p->wait()->getStatusCode());
    }
}
