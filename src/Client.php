<?php
/**
 * @package Chocolife.me
 * @author  Moldabayev Vadim <moldabayev.v@chocolife.kz>
 */

namespace Chocofamily\SmartHttp;

use Psr\SimpleCache\CacheInterface;
use Chocofamily\SmartHttp\Middleware\CacheMiddleware;
use Chocofamily\SmartHttp\Middleware\CircuitBreakerMiddleware;
use Chocofamily\SmartHttp\Storage\CacheStorage;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Phalcon\Cache\BackendInterface;


/**
 * Class Client
 *
 * @package Chocofamily\SmartHttp
 */
class Client extends GuzzleClient
{
    /**
     * @var array
     */
    protected $config;

    /**
     * @var BackendInterface
     */
    protected $cache;

    /**
     * @var array
     */
    protected $options = [];

    /**
     * @var HandlerStack
     */
    protected $stack;

    /**
     * @var Repeater
     */
    protected $repeater;

    /**
     * @var CircuitBreaker
     */
    protected $circuitBreaker;

    /**
     * Client constructor.
     *
     * @param array          $config
     * @param CacheInterface $cache
     */
    public function __construct(array $config, ?CacheInterface $cache = null)
    {
        $this->config = $config;
        $this->cache  = $cache;

        if ($this->cache) {
            $storage = new CacheStorage(
                $this->cache,
                $this->config['lock_time'] ?? Options::LOCK_TIME,
                $this->config['cbKeyPrefix'] ?? 'circuit_breaker'
            );

            $this->circuitBreaker = new CircuitBreaker(
                $storage,
                $this->config['failures'] ?? Options::MAX_FAILURES,
                $this->config['retry_timout'] ?? Options::RETRY_TIMOUT
            );
        }


        $this->repeater = $this->config['repeater'] ??
            new Repeater(
                $this->config['delayRetry'] ?? Options::DELAY_RETRY,
                $this->config['maxRetries'] ?? Options::MAX_RETRIES
            );

        $this->initStack();

        $this->config['connect_timeout'] = $this->config['connect_timeout'] ?? Options::CONNECT_TIMEOUT;
        $this->config['timeout']         = $this->config['timeout'] ?? Options::TIMEOUT;
        $this->config['retries']         = $this->config['init_retries'] ?? Options::INIT_RETRIES;
        $this->config['handler']         = $this->stack;

        parent::__construct($this->config);
    }

    /**
     *
     */
    private function initStack()
    {
        $this->stack = HandlerStack::create($this->config['handler'] ?? null);

        if ($this->cache) {
            $this->stack->push(new CacheMiddleware($this->cache), 'cache');
        }

        if ($this->circuitBreaker) {
            $this->stack->push(new CircuitBreakerMiddleware($this->circuitBreaker), 'circuitBreaker');
        }

        $this->stack->push(
            Middleware::retry($this->repeater->decider(), $this->repeater->delay()), 'repeater'
        );
    }

    /**
     * @return Repeater
     */
    public function getRepeater()
    {
        return $this->repeater;
    }
}
