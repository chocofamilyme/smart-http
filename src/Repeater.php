<?php
/**
 * @package Chocolife.me
 * @author  Moldabayev Vadim <moldabayev.v@chocolife.kz>
 */

namespace Chocofamily\SmartHttp;

class Repeater implements RepeaterInterface
{

    /**
     * @var int
     */
    private $delay = 0;

    /**
     * @var int
     */
    private $attempt = 0;

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
            $this->attempt++;

            return $this->attempt++ < $retries;
        };
    }

    /**
     * PВычисляет задержку между запросами
     *
     * @return \Closure
     */
    public function delay()
    {
        return function (int $retries, $response): int {
            return $retries * $this->delay;
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
    public function getAttempt(): int
    {
        return $this->attempt;
    }
}
