<?php
/**
 * @package Chocolife.me
 * @author  Moldabayev Vadim <moldabayev.v@chocolife.kz>
 */

namespace Chocofamily\SmartHttp;

use GuzzleHttp\Exception\ConnectException;
use Chocofamily\SmartHttp\Http\Response;

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

    public function __construct(int $delayRetry, int $maxRetries = 3)
    {
        $this->delay = $delayRetry;
        $this->maxRetries = $maxRetries;
    }

    /**
     * Указывает нужно ли повторить запрос или нет
     *
     * @return \Closure
     */
    public function decider()
    {
        return function (int $retries, $request, $psrResponse, $error): bool {

            $this->retries = $retries;
            $response = new Response($psrResponse);

            if ($retries < $this->maxRetries &&
                ($response->isServerError() || $error instanceof ConnectException)) {
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
     * @return int
     */
    public function getRetries(): int
    {
        return $this->retries;
    }
}
