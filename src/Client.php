<?php
/**
 * @package Chocolife.me
 * @author  Moldabayev Vadim <moldabayev.v@chocolife.kz>
 */

namespace Chocofamily\SmartHttp;

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
        $storage        = new PhalconCacheAdapter($cache);
        $circuitBreaker = new CircuitBreaker(
            $storage,
            $config->get('failures', Options::MAX_FAILURES),
            $config->get('timeout', Options::TIMEOUT)
        );

        $this->repeater = $config['repeater'] ??
            new Repeater(
                (int) $config->get('delayRetry', Options::DELAY_RETRY),
                (int) $config->get('maxRetries', Options::MAX_RETRIES)
            );

        $stack = HandlerStack::create($config->get('handler'));
        $stack->push(new CircuitBreakerMiddleware($circuitBreaker));
        $stack->push(\GuzzleHttp\Middleware::retry($this->repeater->decider(), $this->repeater->delay()));

        $options                    = $config->toArray();
        $options['timeout']         = $config->get('timeout', Options::TIMEOUT);
        $options['connect_timeout'] = $config->get('connect_timeout', Options::CONNECT_TIMEOUT);
        $options['retries']         = $config->get('init_retries', Options::INIT_RETRIES);
        $options['handler']         = $stack;

        parent::__construct($options);
    }
}
