<?php

/**
 *  Details type for Shopware's API. E.g. Host, Scheme and API Endpoints
 */

namespace eightcontrol\shopware\types;

use Exception;

class site_details
{
    private string $host; // E.g.: example.com
    private string $scheme; // E.g.: https
    private string $oauth_token_endpoint = '/api/oauth/token';
    private string $product_endpoint = '/api/product';
    private string $base_path; // E.g.: /var/www/my-shopware-site/

    private array $default_headers = [
        'user-agent' => 'PHP Admin-API Client',
        'accept' => 'application/json',
        'content-type' => 'application/json'
    ];

    public function __construct(string $base_path, string $host = 'example.com', string $scheme = 'https')
    {
        $this->base_path = $base_path;
        $this->host = $host;
        $this->scheme = $scheme;
    }

    /**
     * Gets or sets the default HTTP headers for the API client
     * @return array 
     */
    public function default_headers(array $headers = null) {
        if (null !== $headers) {
          $this->default_headers = $headers;
        }
        return $this->default_headers;
    }

    /**
     * Gets or sets the host
     * @param string $host 
     * @return shopware_site_detais|string 
     */
    public function host(string $host = null)
    {
        if (null !== $host) {
            $this->host = rtrim(preg_replace("|[a-zA-Z]+://|", '', $host), '/'); // Make sure there is no "scheme://", and no "/" at the end of the host string
            return $this;
        }
        return $this->host;
    }

    /**
     * Gets or sets the scheme
     * @param string $scheme 
     * @return shopware_site_detais|string 
     */
    public function scheme(string $scheme = null)
    {
        if (null !== $scheme) {
            if (0 === preg_match("/^http(s)?$/i", $scheme)) {
                throw new Exception('Scheme must either be "http" or "https".');
            }
            $this->scheme = $scheme;
            return $this;
        }
        return $this->scheme;
    }

    /**
     * Gets or sets the oauth_token_endpoint URL; URL must be relative to the root. E.g. /api/oauth/token
     * @param string $oauth_token_endpoint 
     * @return shopware_site_detais|string 
     */
    public function oauth_token_endpoint(string $oauth_token_endpoint = null)
    {
        if (null !== $oauth_token_endpoint) {
            $this->oauth_token_endpoint = $oauth_token_endpoint;
            return $this;
        }
        return $this->oauth_token_endpoint;
    }

    /**
     * Gets or sets the product endpoint URL; URL must be relative to the root. E.g. /api/product
     * @param string $product_endpoint 
     * @return shopware_site_detais|string|null 
     */
    public function product_endpoint(string $product_endpoint = null)
    {
        if (null !== $product_endpoint) {
            $this->product_endpoint = $product_endpoint;
            return $this;
        }
        return $this->product_endpoint;
    }


    /**
     * Gets or sets the base_path
     * @param string $base_path 
     * @return shopware_site_detais|string 
     */
    public function base_path(string $base_path = null)
    {
        if (null !== $base_path) {
            $this->base_path = $base_path;
            return $this;
        }
        return $this->base_path;
    }
}
