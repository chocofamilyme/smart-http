<?php

namespace Chocofamily\SmartHttp\Http;

use GuzzleHttp\Psr7\Response as PsrResponse;

class Response
{
    const STATUS_CODE_SUCCESS = 200;
    const ERROR_CODE_SUCCESS  = 0;

    /** @var PsrResponse */
    private $response;

    public function __construct($response)
    {
        $this->response = $response;
    }

    public function isServerError()
    {
        if (empty($this->response)) {
            return false;
        }

        if ($this->response->getStatusCode() >= 500
            || $this->getBodyErrorCode() >= 500) {
            return true;
        }

        return false;
    }

    public function isSuccessful()
    {
        return $this->response->getStatusCode() === self::STATUS_CODE_SUCCESS
            && (empty($this->getBodyErrorCode()) || $this->getBodyErrorCode() === self::ERROR_CODE_SUCCESS);
    }

    private function getBodyErrorCode()
    {
        if (!$this->response->getBody()
            || empty($contents = $this->response->getBody()->getContents())) {
            return null;
        }

        $this->response->getBody()->rewind();
        $bodyContent = \json_decode($contents, true);

        return $bodyContent['error_code'] ?? null;
    }
}
