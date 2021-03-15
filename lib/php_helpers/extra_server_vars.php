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
        $_SERVER['request_time'] = time();
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
        // (optional) if test_server_name is defined, use that instead of HTTP_HOST
        $host = (isset($_SERVER['test_server_name'])) ? $_SERVER['test_server_name'] : $_SERVER['HTTP_HOST'];

        $_SERVER['full_request_uri'] = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . $host . $_SERVER['REQUEST_URI'];
    }
    /**
     * Define URL parts
     * @return void
     */
    private function define_url_parts()
    {
        // First, lets parse the REQUEST_URI
        $parsed_uri = parse_url($_SERVER['full_request_uri']);
        // If unable to parse the REQUEST_URI, return false (something dubious is going on with the request?)
        if (false === isset($parsed_uri['path'])) {
            return false;
        }

        // The scheme and host, and nothing more :-)
        $_SERVER['site_base'] = $parsed_uri['scheme'] . '://' . $parsed_uri['host'];
        // full request uri without parameters
        $_SERVER['full_uri_clean'] = $parsed_uri['scheme'] . '://' . $parsed_uri['host'] . $parsed_uri['path'];
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
