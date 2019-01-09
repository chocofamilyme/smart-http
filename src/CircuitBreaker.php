<?php
/**
 * @package Chocolife.me
 * @author  Kamet Aziza <kamet.a@chocolife.kz>
 */

namespace Chocofamily\SmartHttp;

use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Psr\Http\Message\RequestInterface;
use Chocofamily\SmartHttp\Http\Response;

class CircuitBreaker extends \Ejsmont\CircuitBreaker\Core\CircuitBreaker
{
    const CB_TRANSFER_OPTION_KEY = 'circuit_breaker.service_name';

    public function fulfilled(PsrResponse $psrResponse, string $serviceName)
    {
        $response = new Response($psrResponse);

        if ($response->isServerError()) {
            $this->reportFailure($serviceName);
            return;
        }

        $this->reportSuccess($serviceName);
    }

    public function rejected(\Throwable $exception, string $serviceName, callable $exceptionMap)
    {
        if (\call_user_func($exceptionMap, $exception)) {
            $this->reportFailure($serviceName);
            return;
        }

        $this->reportSuccess($serviceName);
    }

    public function serviceName()
    {
        return function (RequestInterface $request, array $options) {
            if (\array_key_exists(self::CB_TRANSFER_OPTION_KEY, $options)) {
                return $options[self::CB_TRANSFER_OPTION_KEY];
            }

            return null;
        };
    }

    public function exceptionMap()
    {
        return function ($rejectedValue) {
            if ($rejectedValue instanceof RequestException && $rejectedValue->getResponse()) {
                return 404 !== $rejectedValue->getResponse()->getStatusCode();
            }

            return true;
        };
    }
}
