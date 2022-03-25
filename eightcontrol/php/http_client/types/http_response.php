<?php

namespace eightcontrol\http_client\types;

class http_response
{

    function __construct($response_data = '', $http_response_header = [], string $raw_request_str = '')
    {
        $status_str = array_shift($http_response_header);
        $this->raw_request_str = $raw_request_str;
        $this->protocol = (false !== ($ptcl = strtok($status_str, ' '))) ? $ptcl : '';
        $this->status_message = (false !== ($stm = strtok(strtok($status_str, ' ')))) ? $stm : '';
        $this->status_code = (false !== ($code = strtok($this->status_message, ' '))) ? (int)$code : '';
        foreach ($http_response_header as $value) {
            $name = strstr($value, ':', true);
            $this->headers["$name"] = trim(substr(strstr($value, ':'), 1));
        }
        $this->data = $response_data;
    }

    /**
     * Returns the status code of the request
     * @return int
     */
    public function status_code() : int
    {
        return $this->status_code;
    }

    /**
     * Returns the status message
     * @return string
     */
    public function status_message() : string
    {
        return $this->status_message;
    }

    /**
     * Returns the bodyEntity of the request
     * @return string
     */
    public function body_entity() : string
    {
        return $this->data;
    }

    /**
     * Returns an array of request headers used in the request
     * @return array
     */
    public function headers() : array
    {
        return $this->headers;
    }

    /**
     * Returns the protocol used for the request. E.g.: https or http
     * @return string
     */
    public function protocol() : string
    {
        return $this->protocol;
    }

    /**
     * Returns the raw text of the HTTP request
     * @return string
     */
    public function raw_request_string() : string
    {
            return $this->raw_request_str;
    }

    private string $protocol;
    private int $status_code;
    private string $status_message;
    private array $headers;
    private string $data;
    private string $raw_request_str;
}