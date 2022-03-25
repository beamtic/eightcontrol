<?php

/**
 * HTTP Client Class for PHP ^^
 *
 * @author Jacob (JacobSeated)
 */

namespace eightcontrol\http_client;

use Exception;
use eightcontrol\http_client\types\{get, post, put, patch, delete, head, http_response};

class http_client implements http_interface
{

    /**
     * Performs a HTTP GET request
     * @param $url
     * @param $parameters
     * @param $headers
     * @return http_response|void
     */
    public function get($url, $parameters = null, $headers = null)
    {
        $message = new get();
        $message->url($url);

        if ($parameters !== null) {
            $message->query_string_parameters($parameters);
        }

        if ($headers !== null) {
            $message->headers($headers);
        }

        return $message->execute();
    }

    /**
     * Performs a HTTP POST request
     * @param $url
     * @param $post_parameters
     * @param $headers
     * @param $entity_body
     * @return http_response
     * @throws Exception
     */
    public function post($url, $post_parameters = null, $headers = null, $entity_body = null): http_response
    {
        $message = new post();
        $message->url($url);

        if ($entity_body !== null) {
            $message->body($entity_body);
            if ($post_parameters !== null) {
                throw new Exception('It is not possible to use post parameters and entity body at the same time.');
            }
        }

        if ($headers !== null) {
            $message->headers($headers);
        }

        return $message->execute();
    }

    /**
     * Performs a HTTP PUT request
     * @param $url
     * @param $headers
     * @param $entity_body
     * @return http_response
     */
    public function put($url, $headers = null, $entity_body = ''): http_response
    {
        $message = new put();
        $message->url($url);

        $message->body($entity_body);

        if ($headers !== null) {
            $message->headers($headers);
        }

        return $message->execute();
    }

    /**
     * Performs a HTTP PATCH request
     * @param mixed $url 
     * @param mixed $headers 
     * @param string $entity_body 
     * @return http_response 
     */
    public function patch($url, $headers = null, $entity_body = ''): http_response
    {
        $message = new patch();
        $message->url($url);

        $message->body($entity_body);

        if ($headers !== null) {
            $message->headers($headers);
        }

        return $message->execute();
    }

    /**
     * Performs a HTTP DELETE request
     * @param string $url
     * @param array|null $headers
     * @return http_response
     */
    public function delete(string $url, array $headers = null): http_response
    {
        $message = new delete();
        $message->url($url);

        if ($headers !== null) {
            $message->headers($headers);
        }

        return $message->execute();
    }

    /**
     * Performs a HTTP HEAD request
     * @param string $url
     * @param array|null $headers
     * @return http_response
     */
    public function head(string $url, array $headers = null): http_response
    {
        $message = new head();
        $message->url($url);

        if ($headers !== null) {
            $message->headers($headers);
        }

        return $message->execute();
    }
}
