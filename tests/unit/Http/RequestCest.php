<?php
/**
 * @package Chocolife.me
 * @author  Kamet Aziza <kamet.a@chocolife.kz>
 */

namespace Unit;

use Chocofamily\SmartHttp\Http\Request;
use GuzzleHttp\Exception\ServerException;
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

        $response = $request->send('POST', 'http://test.com', [], 'test-service');

        $I->assertNotNull($this->cache->getLastKey());
        $I->assertEquals(200, $response->getStatusCode());
    }

    public function tryToSendAsyncRequest(\UnitTester $I)
    {
        $responses = [new Response(200)];
        $request   = $this->getPreparedRequest($responses);

        /** @var Promise $promise */
        $promise  = $request->sendAsync('GET', 'http://test.com', [], 'test-service');
        $response = $promise->wait();

        $I->assertNotNull($this->cache->getLastKey());
        $I->assertEquals(200, $response->getStatusCode());
    }

    public function tryToSendMultipleRequest(\UnitTester $I)
    {
        $responses = [
            function (\GuzzleHttp\Psr7\Request $request, $options) {
                return new Response(200, [], $request->getUri()->getQuery());
            },
            function (\GuzzleHttp\Psr7\Request $request, $options) {
                return new Response(200, [], '');
            },
            function (\GuzzleHttp\Psr7\Request $request, $options) {
                return new Response(200, [], $request->getBody());
            },
            new Response(500),
        ];

        $resultData = [
            'firstService'  => [
                'data' => 'q=test',
            ],
            'secondService' => [
                'data' => '',
            ],
            'thirdService'  => [
                'data' => 'name=name_test&surname=surname_test',
            ],
        ];

        $request = $this->getPreparedRequest($responses, ['maxRetries' => 1]);
        $results = $request->sendMultiple([
            'firstService'  => [
                'method'      => 'GET',
                'path'        => 'http://test.com',
                'data'        => ['q' => 'test'],
                'serviceName' => 'firstService',
            ],
            'secondService' => [
                'method' => 'GET',
                'path'   => 'http://test.com/qwe',
            ],
            'thirdService'  => [
                'method'      => 'POST',
                'path'        => 'http://test.com/asd',
                'data'        => [
                    'name'    => 'name_test',
                    'surname' => 'surname_test',
                ],
                'serviceName' => 'thirdService',
            ],
            'fourthService' => [
                'method' => 'GET',
                'path'   => 'http://test.com/500',
            ],
        ]);

        $I->assertNotNull($results);

        foreach ($results as $key => $result) {
            if ($result['state'] === Request::SUCCESS_STATE) {
                $I->assertEquals(200, $result['value']->getStatusCode());
                $I->assertEquals($resultData[$key]['data'], $result['value']->getBody()->getContents());
            } else {
                $I->assertEquals(ServerException::class, get_class($result['reason']));
            }
        }
        $I->assertNotNull($this->cache->getLastKey());
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
        $config               = \Phalcon\Di::getDefault()->getShared('config')->get('smartHttp', []);
        $config['handler']    = $handler;
        $config['maxRetries'] = 3;

        $config->merge(new Config($params));

        $this->cache = new CacheMock();

        return new Request($config, $this->cache);
    }
}
