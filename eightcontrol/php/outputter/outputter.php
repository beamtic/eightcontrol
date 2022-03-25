<?php

/**
 *      class for showing basic output in the console or in a browser, depending on how the script was requested
 * 
 * @author Jacob (JacobSeated)
 */

namespace eightcontrol\outputter;

use eightcontrol\php_helpers\php_helpers;
use Exception;

class outputter
{

    private php_helpers $helpers;

    public function __construct(php_helpers $php_helpers)
    {
        $this->helpers = $php_helpers;
    }

    /**
     * Either outputs plain text in CLI, or HTML if requested over HTTP. If $input is an array and called over HTTP, a JSON response will be sent. Only supply headers if the script is requested over HTTP.
     * 
     * @param string $input 
     * @param array|null $headers 
     * @return never 
     */
    public function text(string $input, array $headers = null)
    {

        // Attempt to clean output buffers before sending content
        while (ob_get_level() !== 0) {
            ob_clean();
        }

        if (!is_string($input)) {
            // Objects and other unknown types
            $input = print_r($input, true);
        }

        // If in CLI mode
        if ($this->helpers->is_called_from_cli()) {
            $input .= "\n\n";
            echo $input;
            exit();
        } else {
            // Assume PHP is called via a web browser and use HTML instead
            http_response_code(200);
            $this->send_headers($headers);
            echo $input;
            exit();
        }
    }

    /**
     * Outputs an array as a JSON encoded string. Optionally supply headers when the script is requested over HTTP.
     * @param array $array 
     * @param mixed $headers 
     * @return never 
     */
    public function json(array $array, $headers = null)
    {
        if (!$this->helpers->is_called_from_cli()) {
            $this->send_headers($headers + [
                'content-type' => 'application/json; charset=utf-8',
                // Yeah, let's not cache JSON responses, right? :-p
                'cache-control' => 'no-cache, no-store, must-revalidate',
                'pragma' => 'no-cache', // HTTP 1.0
                'expires' => '0'
            ]);
        }

        if (false === ($json = json_encode($array))) {
          throw new Exception("Failed to encode JSON.");
        }

        echo $json;
        exit();
    }

    /**
     * Sends the HTTP headers either as provided or the predefined default headers
     * @param array $custom_headers 
     * @return void 
     */
    private function send_headers(array $custom_headers = null)
    {

        $http_response_headers = [
            'content-type' => 'text/html; charset=utf-8',
            'cache-control' => 'no-cache, no-store, must-revalidate', // HTTP 1.1.
            'pragma' => 'no-cache', // HTTP 1.0
            'expires' => '0'
        ];

        if ($custom_headers !== null) {
            $http_response_headers = $custom_headers;
        }

        foreach ($http_response_headers as $name => $value) {
            header($name . ': ' . $value);
        }
    }
}
