<?php
/**
 * @package Chocolife.me
 * @author  Moldabayev Vadim <moldabayev.v@chocolife.kz>
 */

namespace Chocofamily\SmartHttp;

use Ejsmont\CircuitBreaker\Core\CircuitBreaker;
use Ejsmont\CircuitBreaker\Storage\Adapter\DummyAdapter;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\HandlerStack;
use Psr\Http\Message\RequestInterface;
use GuzzleHttp\Exception\RequestException;

class Client extends GuzzleClient
{
    public $repeater;

    public function __construct(\Phalcon\Config $config)
    {
        $storage = new DummyAdapter();
        $circuitBreaker = new CircuitBreaker(
            $storage,
            $config->get('failures', Options::MAX_FAILURES),
            $config->get('timeout', Options::TIMEOUT)
        );

        $handler  = isset($config['handler']) ? $config['handler'] : new CurlHandler();
        $this->repeater = isset($config['repeater']) ? $config['repeater'] : new Repeater(
            (int) $config->get('delayRetry', Options::DELAY_RETRY)
        );
        $this->repeater->setMaxRetries($config->get('maxRetries', Options::MAX_RETRIES));

        $stack = HandlerStack::create($handler);
        $stack->push(Middleware::circuitBreaker($circuitBreaker, $this->serviceName(), $this->exceptionMap()));
        //$stack->push(\GuzzleHttp\Middleware::retry($this->repeater->decider(), $this->repeater->delay()));

        $options                    = $config->toArray();
        $options['timeout']         = $config->get('timeout', Options::TIMEOUT);
        $options['connect_timeout'] = $config->get('connect_timeout', Options::CONNECT_TIMEOUT);
        $options['retries'] = $config->get('init_retries', Options::INIT_RETRIES);
        $options['handler']         = $stack;

        parent::__construct($options);
    }

    private function serviceName()
    {
        return function (RequestInterface $request, array $options) {
            if (\array_key_exists('my_custom_option_key', $options)) {
                return $options['my_custom_option_key'];
            }

            return null;
        };
    }

    private function exceptionMap()
    {
        return function ($rejectedValue) {
            if ($rejectedValue instanceof RequestException && $rejectedValue->getResponse()) {
                return 404 !== $rejectedValue->getResponse()->getStatusCode();
            }

            return true;
        };
    }
}
