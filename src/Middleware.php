<?php
/**
 * @package Chocolife.me
 * @author  Moldabayev Vadim <moldabayev.v@chocolife.kz>
 */

namespace Chocofamily\SmartHttp;

use Ejsmont\CircuitBreaker\CircuitBreakerInterface;
use FlixTech\CircuitBreakerMiddleware\Middleware as CircuitBreakerMiddleware;
use Ejsmont\CircuitBreaker\Factory as CircuitBreakerFactory;

final class Middleware
{
    public static function circuitBreaker(
        CircuitBreakerInterface $circuitBreaker = null,
        callable $serviceNameExtractor = null,
        callable $exceptionMap = null
    ) {
        if (!$circuitBreaker) {
            $circuitBreaker = CircuitBreakerFactory::getSingleApcInstance();
        }

        return new CircuitBreakerMiddleware(
            $circuitBreaker,
            $serviceNameExtractor,
            $exceptionMap
        );
    }
}
