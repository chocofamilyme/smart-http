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
use Psr\SimpleCache\CacheInterface;

class CacheCest
{
    /** @var CacheInterface */
    private $cache;

    /**
     * @param \UnitTester $I
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    protected function tryToSaveIntoCache(\UnitTester $I)
    {
        $I->wantToTest('сохранить в кэш');

        $responses = [new Response(200)];

        $method = 'GET';
        $uri    = 'test.com';
        $key    = CacheMiddleware::DEFAULT_KEY_PREFIX.md5($method.$uri);

        $request = $this->getPreparedRequest($responses);
        $request->send($method, $uri, ['cache' => 60]);

        $I->assertTrue($this->cache->has($key));
    }

    /**
     * @param \UnitTester $I
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    protected function tryToNotSaveIntoCacheIfPost(\UnitTester $I)
    {
        $I->wantToTest('не сохранять в кэш если запрос POST');

        $responses = [
            new Response(200),
            new Response(200),
        ];

        $method = 'POST';
        $uri    = 'test.com';
        $key    = CacheMiddleware::DEFAULT_KEY_PREFIX.md5($method.$uri);

        $request = $this->getPreparedRequest($responses);

        $request->send($method, $uri, ['cache' => 60]);
        $I->assertFalse($this->cache->has($key));
    }

    /**
     * @param \UnitTester $I
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    protected function tryToNotSaveIntoCacheIfNoLifetime(\UnitTester $I)
    {
        $I->wantToTest('не сохранять в кэш если не указан lifetime');

        $responses = [
            new Response(200),
        ];

        $method = 'GET';
        $uri    = 'test.com';
        $key    = CacheMiddleware::DEFAULT_KEY_PREFIX.md5($method.$uri);

        $request = $this->getPreparedRequest($responses);

        $request->send($method, $uri);
        $I->assertFalse($this->cache->has($key));
    }

    protected function tryToGetResponseFromCache(\UnitTester $I)
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
        $result = $request->send($method, $uri, ['cache' => 60]);

        $I->assertEquals(200, $result->getStatusCode());
        $I->assertEquals($body, $result->getBody()->getContents());
    }

    protected function tryToNotSaveServerErrorIntoCache(\UnitTester $I)
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

        $I->expectThrowable(ServerException::class, function () use ($request, $method, $uri) {
            $request->send($method, $uri, ['cache' => 60]);
        });
        $result = $request->send($method, $uri, ['cache' => 60]);

        $I->assertEquals(200, $result->getStatusCode());
        $I->assertEquals($header, $result->getHeaders());
        $I->assertEquals($body, $result->getBody()->getContents());
    }

    protected function tryToNotSaveApiErrorIntoCache(\UnitTester $I)
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
        $result = $request->send($method, $uri, ['cache' => 60]);

        $I->assertEquals(200, $result->getStatusCode());
        $I->assertEquals($body, $result->getBody()->getContents());
    }

    /**
     * @dataprovider dataProvider
     *
     * @param \UnitTester          $I
     * @param \Codeception\Example $data
     *
     * @throws \ReflectionException
     */
    public function tryToClearUrl(\UnitTester $I, \Helper\Unit $helper, \Codeception\Example $data)
    {

        $cacheMiddleware = new CacheMiddleware(new CacheMock());

        $url = $helper->invokeMethod($cacheMiddleware, 'clearUrl', [$data['url']]);

        $I->assertEquals($data['expected'], $url);
    }

    protected function dataProvider()
    {
        return [
            [
                'url'      => 'http://users.vadim.chocodev.kz/user/towns?correlation_id=931fa704-15bf-409d-b108-299b7b5c942c&span_id=1',
                'expected' => 'http://users.vadim.chocodev.kz/user/towns',
            ],
            [
                'url'      => 'http://users.vadim.chocodev.kz/user/towns?correlation_id=931fa704-15bf-409d-b108-299b7b5c942c&span_id=1&key=value',
                'expected' => 'http://users.vadim.chocodev.kz/user/towns?key=value',
            ],
            [
                'url'      => 'http://users.vadim.chocodev.kz/user/towns?key=value&correlation_id=931fa704-15bf-409d-b108-299b7b5c942c&amp;span_id=2',
                'expected' => 'http://users.vadim.chocodev.kz/user/towns?key=value',
            ],
        ];
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

        /** @var array $config */
        $config               = [];
        $config['handler']    = $handler;
        $config['maxRetries'] = 3;

        $config      = array_merge($config, $params);
        $this->cache = new CacheMock();

        return new Request($config, $this->cache);
    }
}
