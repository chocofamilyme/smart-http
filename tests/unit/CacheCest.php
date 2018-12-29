<?php
/**
 * @package Chocolife.me
 * @author  Kamet Aziza <kamet.a@chocolife.kz>
 */

namespace Unit;

use Chocofamily\SmartHttp\Http\Request;
use Chocofamily\SmartHttp\Middleware\CacheMiddleware;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use Helper\SmartHttp\CacheMock;
use Phalcon\Cache\BackendInterface;
use Phalcon\Config;

class CacheCest
{
    /** @var BackendInterface */
    private $cache;

    public function tryToSaveIntoCache(\UnitTester $I)
    {
        $I->wantToTest('сохранить в кэш');

        $responses = [new Response(200)];

        $method = 'GET';
        $uri    = 'test.com';
        $key    = CacheMiddleware::CACHE_KEY_PREFIX.md5($method.$uri);

        $request = $this->getPreparedRequest($responses);
        $request->send($method, $uri, ['cache' => 60]);

        $I->assertTrue($this->cache->exists($key));
    }

    public function tryToNotSaveIntoCache(\UnitTester $I)
    {
        $I->wantToTest('не сохранять в кэш');

        $responses = [
            new Response(200),
            new Response(200),
        ];

        $method = 'POST';
        $uri    = 'test.com';
        $key    = CacheMiddleware::CACHE_KEY_PREFIX.md5($method.$uri);

        $request = $this->getPreparedRequest($responses);

        $request->send($method, $uri);
        $I->assertFalse($this->cache->exists($key));

        $request->send($method, $uri, ['cache' => 60]);
        $I->assertFalse($this->cache->exists($key));
    }

    public function tryToGetResponseFromCache(\UnitTester $I)
    {
        $I->wantToTest('получить ответ из кэша');

        $body      = json_encode([
            'error_code' => 0,
            'status'     => 'success',
            'message'    => 'Success!',
            'data'       => [
                'name'    => 'asd',
                'surname' => 'qwe',
            ],
        ]);
        $responses = [
            new Response(200, [], $body),
            new Response(500),
        ];

        $method = 'GET';
        $uri    = 'test.com';

        $request = $this->getPreparedRequest($responses);
        $request->send($method, $uri, ['cache' => 60]);

        $result = $request->send($method, $uri);
        $I->assertEquals(200, $result->getStatusCode());
        $I->assertEquals($body, $result->getBody()->getContents());
    }

    public function tryToNotSaveServerErrorIntoCache(\UnitTester $I)
    {
        $I->wantToTest('не сохранять при ошибке сервера');

        $body      = json_encode([
            'error_code' => 0,
            'status'     => 'success',
            'message'    => 'Success!',
            'data'       => [
                'name'    => 'asd',
                'surname' => 'qwe',
            ],
        ]);
        $header    = [
            'X-Test-Header' => ['test'],
        ];
        $responses = [
            new Response(500),
            new Response(200, $header, $body),
        ];

        $method = 'GET';
        $uri    = 'test.com';

        $request = $this->getPreparedRequest($responses);

        $I->expectException(ServerException::class, function () use ($request, $method, $uri) {
            $request->send($method, $uri, ['cache' => 60]);
        });

        $result = $request->send($method, $uri);
        $I->assertEquals(200, $result->getStatusCode());
        $I->assertEquals($header, $result->getHeaders());
        $I->assertEquals($body, $result->getBody()->getContents());
    }

    public function tryToNotSaveApiErrorIntoCache(\UnitTester $I)
    {
        $I->wantToTest('не сохранять при ошибке API');

        $errorBody = json_encode([
            'error_code' => 404,
            'status'     => 'error',
            'message'    => 'Error!',
            'data'       => null,
        ]);
        $body      = json_encode([
            'error_code' => 0,
            'status'     => 'success',
            'message'    => 'Success!',
            'data'       => null,
        ]);
        $responses = [
            new Response(200, [], $errorBody),
            new Response(200, [], $body),
        ];

        $method = 'GET';
        $uri    = 'test.com';

        $request = $this->getPreparedRequest($responses);
        $request->send($method, $uri, ['cache' => 60]);
        $result = $request->send($method, $uri);

        $I->assertEquals(200, $result->getStatusCode());
        $I->assertEquals($body, $result->getBody()->getContents());
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
        $config['maxRetries'] = 1;

        $config->merge(new Config($params));

        $this->cache = new CacheMock();

        return new Request($config, $this->cache);
    }
}
