<?php

namespace Chocofamily\SmartHttp\Http;

use GuzzleHttp\Psr7\Response as PsrResponse;

class Response
{
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

        if ($this->response->getStatusCode() >= 500) {
            return true;
        }

        return false;
    }

    public function isSuccessful()
    {
        $status = $this->response->getStatusCode();

        return $status >= 200 && $status < 300;
    }
}
