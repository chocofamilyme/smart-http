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

        if ($this->response->getBody() &&
            !empty($contents = $this->response->getBody()->getContents())) {
            $this->response->getBody()->rewind();
            $bodyContent = \json_decode($contents, true);

            if (isset($bodyContent['error_code']) &&
                $bodyContent['error_code'] >= 500) {
                return true;
            }
        }

        return false;
    }
}
