<?php

namespace Prestashop\ModuleLibMboInstaller;

class Response
{
    /** @var int */
    private $statusCode;
    /** @var string */
    private $body;
    /** @var array<string,string> */
    private $headers;

    /**
     * @param int $statusCode
     * @param string $body
     * @param array<string,string> $headers
     **/
    public function __construct($statusCode, $body, $headers = [])
    {
        $this->statusCode = $statusCode;
        $this->body = $body;
        $this->headers = $headers;
    }

    /**
     * @return int
     */
    public function getStatusCode()
    {
        return $this->statusCode;
    }

    /**
     * @return array<string,string>
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * @return string
     */
    public function getBody()
    {
        return $this->body;
    }

    /**
     * @return bool
     */
    public function isSuccessful()
    {
        return substr((string) $this->statusCode, 0, 1) == '2';
    }
}
