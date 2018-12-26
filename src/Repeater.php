<?php
/**
 * @package Chocolife.me
 * @author  Moldabayev Vadim <moldabayev.v@chocolife.kz>
 */

namespace Chocofamily\SmartHttp;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Response;

class Repeater implements RepeaterInterface
{

    /**
     * @var int
     */
    private $delay = 0;

    /**
     * @var int
     */
    private $maxRetries = 3;

    /**
     * @var int
     */
    private $retries;

    public function __construct(int $delayRetry)
    {
        $this->delay = $delayRetry;
    }

    /**
     * Указывает нужно ли повторить запрос или нет
     *
     * @return \Closure
     */
    public function decider()
    {
        return function (int $retries, $request, $response, $error): bool {

            $this->retries = $retries;

            if ($retries < $this->maxRetries &&
                ($this->isServerError($response) || $this->isConnectionError($error))) {
                return true;
            }

            return false;
        };
    }

    /**
     * Вычисляет задержку между запросами
     *
     * @return \Closure
     */
    public function delay()
    {
        return function (int $retries, $response): int {
            return $retries * $this->delay + 100;
        };
    }

    /**
     * @return int
     */
    public function getDelay(): int
    {
        return $this->delay;
    }

    /**
     * @return int
     */
    public function getMaxRetries(): int
    {
        return $this->maxRetries;
    }

    /**
     * @param int $maxRetries
     */
    public function setMaxRetries(int $maxRetries): void
    {
        $this->maxRetries = $maxRetries;
    }

    /**
     * @param Response $response
     *
     * @return bool
     */
    private function isServerError($response)
    {
        if (empty($response)) {
            return false;
        }

        if ($response->getStatusCode() >= 500) {
            return true;
        }

        if ($response->getBody() &&
            !empty($contents = $response->getBody()->getContents())) {
            $response->getBody()->rewind();
            $bodyContent = \json_decode($contents, true);

            if (isset($bodyContent['error_code']) &&
                $bodyContent['error_code'] >= 500) {
                return true;
            }
        }

        return false;
    }

    private function isConnectionError($error)
    {
        return $error instanceof ConnectException;
    }

    /**
     * @return int
     */
    public function getRetries(): int
    {
        return $this->retries;
    }
}
