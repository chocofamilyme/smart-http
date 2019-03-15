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
use Phalcon\Cache\BackendInterface;

class Client extends GuzzleClient
{
    public $repeater;

    public function __construct(\Phalcon\Config $config, BackendInterface $cache)
    {
        $storage = new PhalconCacheAdapter(
            $cache,
            $config->get('lock_time', Options::LOCK_TIME),
            $config->get('cbKeyPrefix', 'circuit_breaker')
        );

        $circuitBreaker = new CircuitBreaker(
            $storage,
            $config->get('failures', Options::MAX_FAILURES),
            $config->get('lock_time', Options::LOCK_TIME)
        );

        $this->repeater = $config['repeater'] ??
            new Repeater(
                (int) $config->get('delayRetry', Options::DELAY_RETRY),
                (int) $config->get('maxRetries', Options::MAX_RETRIES)
            );

        $stack = HandlerStack::create($config->get('handler'));
        $stack->push(new CacheMiddleware($cache), 'cache');
        $stack->push(new CircuitBreakerMiddleware($circuitBreaker), 'circuitBreaker');
        $stack->push(\GuzzleHttp\Middleware::retry($this->repeater->decider(), $this->repeater->delay()), 'repeater');

        $options                    = $config->toArray();
        $options['timeout']         = $config->get('timeout', Options::TIMEOUT);
        $options['connect_timeout'] = $config->get('connect_timeout', Options::CONNECT_TIMEOUT);
        $options['retries']         = $config->get('init_retries', Options::INIT_RETRIES);
        $options['handler']         = $stack;

        parent::__construct($options);
    }
}
