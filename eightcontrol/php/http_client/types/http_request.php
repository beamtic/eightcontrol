<?php

namespace eightcontrol\http_client\types;

/**
 *  Used as a base class for HTTP request types such as GET, POST, PUT, DELETE
 *
 */
abstract class http_request
{
    protected array $query_string_parameters; // Array of query string parameters to be appended to the URL
    protected string $query_string = ''; // The query string contains the string version of the $qsParameters array
    protected array $default_req_headers = ['user-agent' => 'PHP'];
    protected array $headers = []; // Array of headers to send along with the request. Note. PHP and/or your specific setup might add its own custom headers that can not be overridden from here.
    protected string $headers_string = ''; // The headers as a string
    protected string $url; // The pending URL to be contacted
    protected string $entity_body = ''; // The body part of the request is sent after the headers
    protected string $type; // The request type to be used. E.g. POST, GET, PUT, DELETE

    protected http_request $last_request;

    function __construct()
    {

    }

    /**
     * Sets or returns the url to request
     * @param string|null $url
     * @return http_request|string
     */
    public function url(string $url = null) {
        if ($url === null) {
            return $this->url;
        }
        $this->url = $url;
        return $this;
    }

    /**
     * Sets or gets the URL (Aka. Query String or GET) parameters as a String or an Array
     * @param array|null $query_string_parameters
     * @param bool $array
     * @return array
     */
    public function query_string_parameters(array $query_string_parameters = null, bool $array = false) : array {
        if ($query_string_parameters === null) {
            if ($array === true) {
                return $this->query_string_parameters;
            } else {
                return $this->query_string;
            }
        }
        $this->query_string_parameters = $query_string_parameters;
        $this->queryString = $this->build_query_string($query_string_parameters + $this->default_req_headers);
        return $this;
    }

    /**
     * Sets or gets the headers of the request
     * @param array|null $headers
     * @param bool $array
     * @return http_request|array|string
     */
    public function headers(array $headers = null, bool $array = false) {
        if ($headers === null) {
            if ($array === true) {
                return $this->headers;
            } else {
                return $this->headers_string;
            }
        }
        $this->headers = $headers;
        $this->headers_string = $this->build_header_string($headers + $this->default_req_headers);

        return $this;
    }
    /**
     * Sets or gets the request type. E.g. POST, GET, PUT, DELETE
     * @param string|null $type
     * @return string|http_request
     */
    public function type(string $type = null) {
        if ($type === null) {
           return $this->type;
        }
        $this->type = $type;
        return $this;
    }


    /**
     * Executes a HTTP request.
     * @return http_response
     */
    public function execute() : http_response {

        $this->headers_string = $this->build_header_string($this->headers + $this->default_req_headers);

        // HTTP wrapper options to be used with the stream context_create() function
        $options = array(
            'ignore_errors' => true,
            'method'  => $this->type,
            'header'  => $this->headers_string
        );

        // If the entity body is present, add it to the options.
        // Note. Non-POST request types can also contain an entity body;
        // usually the entity body only contain POST parameters.
        if (!empty($this->entity_body)) {
            $options['content'] = $this->entity_body;
        }

        // Select the appropriate wrapper. E.g. "http" or "ftp". Of course, options must also match the wrapper used.
        $http_wrapper = array(
            'http' => $options
        );

        // Perform the HTTP request using file_get_contents() and stream_context_create()
        $response_data = file_get_contents($this->url, false, stream_context_create($http_wrapper));

        // Clean up
        $this->last_request = $this;

        return new http_response($response_data, $http_response_header, $this->show_raw_request());
    }

    /**
     * Converts an array of request headers to a correctly formatted string
     * @param array $parameters
     * @return string
     */
    private function build_header_string(array $parameters) : string
    {
        if (count($parameters) < 1) {
            return '';
        }

        $parm_string = '';
        foreach ($parameters as $key => $value) {
            $parm_string .= $key . ': ' . $value . "\r\n";
        }

        return $parm_string;
    }

    /**
     * Defines the entity body of a request. Warning: Only use this if you need raw access to the entity body; Note: Other request types than POST can contain an entity body.
     * @param string|null $entityBody
     * @return http_request|string
     */
    public function body(string $entity_body = null) {
        if ($entity_body === null) {
            return $this->entity_body;
        }
        $this->entity_body = $entity_body;
        return $this;
    }

    /**
     * Converts an array of query string parameters to a correctly formatted string
     * @param array $parameters
     * @return string
     */
    private function build_query_string(array $parameters) : string
    {

        if (count($parameters) < 1) {
            return '';
        }

        $query_string = '?';
        foreach ($parameters as $key => $value) {
            $query_string .= $key . '=' . $value . '&';
        }
        return rtrim($query_string, '&');
    }

    /**
     * Converts an array of post parameters to a correctly formatted string
     * @param array $post_parameters
     * @return string
     */
    protected function build_post_string(array $post_parameters) : string
    {
        // Note. This method may be used by other request types than "post", since the HTTP protocol does allow
        // the presence of an entity body for other request types. So, do not move this to the "types/post.php" file!

        if (count($post_parameters) < 1) {
            return '';
        }

        $entity_body_string = key($post_parameters) . '=' . current($post_parameters);
        if (!next($post_parameters)) {
            return $entity_body_string;
        }
        foreach ($post_parameters as $key => $value) {
            $entity_body_string .= '&' . $key . '=' . $value;
        }
        return $entity_body_string;
    }

    /**
     * Returns the estimated raw text of the request. Note. The server might add its own headers, so you may want to test against an external service. E.g.: https://beamtic.com/api/raw-request
     * @return string
     */
    public function show_raw_request() : string {
        return $this->headers_string . "\r\n\r\n" . $this->entity_body;
    }

    /**
     * Returns the last request as a httpRequest object.
     * @return http_request
     */
    public function last_request() : http_request {
        return $this->last_request;
    }


}