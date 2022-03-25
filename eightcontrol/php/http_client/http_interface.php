<?php
/**
 *  Interface for the HTTP implementation
 *
 * @author Jacob (JacobSeated)
 */

namespace eightcontrol\http_client;

interface http_interface {
    public function get(string $url, array $parameters = null, array $headers = null);
    public function post(string $url, array $post_parameters = null, array $headers = null, string $entity_body = null);
    public function put(string $url, array $headers = null, string $entity_body = '');
    public function delete(string $url, array $headers = null);
    public function head(string $url, array $headers = null);
}