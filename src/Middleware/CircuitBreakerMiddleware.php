<?php

namespace Chocofamily\SmartHttp\Middleware;

use Chocofamily\SmartHttp\Exception\CircuitIsClosedException;
use Chocofamily\SmartHttp\CircuitBreaker;
use GuzzleHttp\Promise\Create;
use Psr\Http\Message\RequestInterface;

class CircuitBreakerMiddleware
{
    const CB_SERVICE_NAME_HEADER = 'X-CB-Service-Name';
    const CB_TRANSFER_OPTION_KEY = 'circuit_breaker.requested_service_name';

    /**
     * @var CircuitBreaker
     */
    private $circuitBreaker;

    /**
     * @var callable
     */
    private $exceptionMap;

    /**
     * @var callable
     */
    private $serviceNameExtractor;

    public function __construct(CircuitBreaker $circuitBreaker)
    {
        $this->circuitBreaker = $circuitBreaker;
        $this->exceptionMap = $circuitBreaker->exceptionMap();
        $this->serviceNameExtractor = $circuitBreaker->serviceName();

        if (!$this->exceptionMap) {
            $this->exceptionMap = function (): bool {
                return true;
            };
        }

        if (!$this->serviceNameExtractor) {
            $this->serviceNameExtractor = function (RequestInterface $request, array $requestOptions): string {
                $serviceName = '';

                if (\array_key_exists(self::CB_TRANSFER_OPTION_KEY, $requestOptions)) {
                    $serviceName = $requestOptions[self::CB_TRANSFER_OPTION_KEY];
                }

                if (!$serviceName) {
                    $header = $request->getHeader(self::CB_SERVICE_NAME_HEADER);

                    if (0 !== \count($header)) {
                        $serviceName = $header[0];
                    }
                }

                return $serviceName;
            };
        }
    }

    public function __invoke(callable $handler): callable
    {
        return function (RequestInterface $request, array $requestOptions) use ($handler) {
            $serviceName = \call_user_func($this->serviceNameExtractor, $request, $requestOptions);

            if (!$serviceName) {
                return $handler($request, $requestOptions);
            }

            if (!$this->circuitBreaker->isAvailable($serviceName)) {
                return Create::rejectionFor(
                    new CircuitIsClosedException(
                        sprintf('Circuit for service "%s" is closed', $serviceName)
                    )
                );
            }

            /** @var \GuzzleHttp\Promise\PromiseInterface $promise */
            $promise = $handler($request, $requestOptions);

            return $promise->then(
                function ($value) use ($serviceName) {
                    $this->circuitBreaker->fulfilled($value, $serviceName);

                    return Create::promiseFor($value);
                },
                function ($reason) use ($serviceName) {
                    $this->circuitBreaker->rejected($reason, $serviceName, $this->exceptionMap);

                    return Create::rejectionFor($reason);
                }
            );
        };
    }
}
