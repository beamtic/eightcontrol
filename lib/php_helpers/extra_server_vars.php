<?php

/**
 *           Doorkeeper Globals
 *
 *               Problem
 *                The $_SERVER array has inconsistencies and missing variables depending on the system running PHP, and could therefor use a few fixes.
 *                this class will add fixes as extra variables, without modifying existing variables.
 * 
 *                Note. This class should be loaded before the superglobals class (if used)
 *
 *
 *         @author Jacob (JacobSeated)
 */

namespace doorkeeper\lib\php_helpers;

class extra_server_vars
{
    public function __construct(string $BASE_PATH)
    {
        $this->define_request_protocol();
        $this->define_full_request_uri();
        $this->define_url_parts();
        $_SERVER['base_path'] = $BASE_PATH;
    }
    /**
     * Define the request_protocol (since $_SERVER['HTTPS'] is not consistent)
     * Reason: Some servers will set HTTPS=off instead of an empty value
     * @return void
     */
    private function define_request_protocol()
    {
        $_SERVER['request_protocol'] = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http');
    }
    /**
     * Define the full_request_uri
     * @return void
     */
    private function define_full_request_uri()
    {
        $_SERVER['full_request_uri'] = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    }
    /**
     * Define URL parts
     * @return void
     */
    private function define_url_parts()
    {
        // First, lets parse the REQUEST_URI
        $parsed_uri = parse_url($_SERVER['REQUEST_URI']);

        // The request_path only contains the path, without GET parameters.
        $_SERVER['request_path'] = $parsed_uri['path'];
        // The query string is parsed automatically by PHP
        // and is available in the $_GET superglobal
        $_SERVER['query_string'] = (!empty($parsed_uri['query']) ? $parsed_uri['query'] : '');
        // The work_path is the path for the current forward slash location
        // I.e.: /some/path/
        $_SERVER['work_path'] = substr($_SERVER['request_path'], 0, strrpos($_SERVER['request_path'], '/'));
    }

    use \doorkeeper\lib\class_traits\no_set;
}
