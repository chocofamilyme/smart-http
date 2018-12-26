<?php
/**
 * @package Chocolife.me
 * @author  Kamet Aziza <kamet.a@chocolife.kz>
 */

namespace Unit;

use Chocofamily\SmartHttp\Http\Request;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Psr7\Response;
use Helper\SmartHttp\CacheMock;
use Phalcon\Cache\BackendInterface;
use Phalcon\Config;

class RequestCest
{
    /** @var BackendInterface */
    private $cache;

    public function tryToSendSyncRequest(\UnitTester $I)
    {
        $responses = [new Response(200)];
        $request   = $this->getPreparedRequest($responses);

        $response = $request->send('POST', 'http://test.com');

        $I->assertNotNull($request->getServiceName());
        $I->assertNotNull($this->cache->getLastKey());
        $I->assertEquals(200, $response->getStatusCode());
    }

    public function tryToSendAsyncRequest(\UnitTester $I)
    {
        $responses = [new Response(200)];
        $request   = $this->getPreparedRequest($responses);

        /** @var Promise $promise */
        $promise  = $request->sendAsync('GET', 'http://test.com');
        $response = $promise->wait();

        $I->assertNotNull($request->getServiceName());
        $I->assertNotNull($this->cache->getLastKey());
        $I->assertEquals(200, $response->getStatusCode());
    }

    public function tryToSendMultipleRequest(\UnitTester $I)
    {

        $responses = [
            new Response(200),
            new Response(200),
            new Response(200),
        ];
        $request   = $this->getPreparedRequest($responses);

        /** @var Promise $promise */
        $results = $request->sendMultiple([
            'http://test.com',
            'http://test.com/asd',
            'http://test.com/qwe',
        ]);

        $I->assertNotNull($results);

        foreach ($results as $result) {
            /** @var Response $result */
            $I->assertEquals(200, $result->getStatusCode());
        }
    }

    /**
     * @param       $responses
     * @param array $params
     *
     * @return Request
     */
    private function getPreparedRequest($responses, $params = [])
    {
        $handler = new MockHandler($responses);

        /** @var Config $config */
        $config                = \Phalcon\Di::getDefault()->getShared('config')->get('smartHttp', []);
        $config['handler']     = $handler;
        $config['maxRetries']  = 3;
        $config['serviceName'] = 'test-service';

        $config->merge(new Config($params));

        $this->cache = new CacheMock();

        return new Request($config, $this->cache);
    }
}
