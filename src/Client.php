<?php
/**
 * @package Chocolife.me
 * @author  Moldabayev Vadim <moldabayev.v@chocolife.kz>
 */

namespace Chocofamily\SmartHttp;

use Chocofamily\SmartHttp\Middleware\CacheMiddleware;
use Chocofamily\SmartHttp\Middleware\CircuitBreakerMiddleware;
use Chocofamily\SmartHttp\Storage\PhalconCacheAdapter;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Phalcon\Cache\BackendInterface;
use Phalcon\Config;

class Client extends GuzzleClient
{
    /**
     * @var Config
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
     * @param Config           $config
     * @param BackendInterface $cache
     */
    public function __construct(Config $config, BackendInterface $cache)
    {
        $this->config = $config;
        $this->cache  = $cache;

        $storage = new PhalconCacheAdapter(
            $this->cache,
            $this->config->get('lock_time', Options::LOCK_TIME),
            $this->config->get('cbKeyPrefix', 'circuit_breaker')
        );

        $this->circuitBreaker = new CircuitBreaker(
            $storage,
            $this->config->get('failures', Options::MAX_FAILURES),
            $this->config->get('retry_timout', Options::RETRY_TIMOUT)
        );

        $this->repeater = $this->config['repeater'] ??
            new Repeater(
                (int) $this->config->get('delayRetry', Options::DELAY_RETRY),
                (int) $this->config->get('maxRetries', Options::MAX_RETRIES)
            );

        $this->initStack();

        $this->options                    = $this->config->toArray();
        $this->options['timeout']         = $this->config->get('timeout', Options::TIMEOUT);
        $this->options['connect_timeout'] = $this->config->get('connect_timeout', Options::CONNECT_TIMEOUT);
        $this->options['retries']         = $this->config->get('init_retries', Options::INIT_RETRIES);
        $this->options['handler']         = $this->stack;

        parent::__construct($this->options);
    }

    /**
     *
     */
    private function initStack()
    {
        $this->stack = HandlerStack::create($this->config->get('handler'));
        $this->stack->push(new CacheMiddleware($this->cache), 'cache');
        $this->stack->push(new CircuitBreakerMiddleware($this->circuitBreaker), 'circuitBreaker');
        $this->stack->push(Middleware::retry($this->repeater->decider(), $this->repeater->delay()),
            'repeater');
    }

    /**
     * @return Repeater
     */
    public function getRepeater()
    {
        return $this->repeater;
    }

    /**
     * @param Repeater $repeater
     */
    public function setRepeater($repeater)
    {
        $this->repeater = $repeater;
    }
}
