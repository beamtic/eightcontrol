<?php

/**
 *   OAuth 2.0 client credentials authentication
 * 
 */

namespace eightcontrol\shopware\types;

use Exception;

/**
 * OAuth 2.0 auth type for Shopware Integrations. You can Create new application id and secret on myShopwareSite.tld/admin#/sw/integration/index
 * @package eightcontrol\shopware\types
 */
class client_credentials extends auth_abstract_base
{
    private string $application_id;
    private string $application_secret;

    /**
     * Attempt to obtain a new Oauth token. Note. Using token() will totally automate obtaining, reusing, and renewing tokens
     * @return api_token 
     * @throws Exception 
     */
    public function oauth_obtain_token(): api_token
    {
        if ((!isset($this->application_id)) || (!isset($this->application_secret))) {
            new Exception('Please provide a application_id and application_secret before trying to authenticate with the client_credentials grant type.');
        }

        $response = $this->http->post(
            $this->d->scheme() . '://' . $this->d->host() . $this->d->oauth_token_endpoint(),
            null,
            $this->d->default_headers(),
            json_encode([
                "grant_type" => "client_credentials",
                "client_id" => $this->application_id,
                "client_secret" => $this->application_secret
            ])
        );

        return $this->save_token($response->body_entity());
    }

    /**
     * Gets or sets the application_id
     * @return client_credentials|string
     */
    public function application_id(string $application_id = null)
    {
        if (null !== $application_id) {
            $this->application_id = $application_id;
            $this->oauth_tmp_file_path = $this->d->base_path() . 'tmp/shopware/' . $this->application_id . '.json';
            return $this;
        }
        return $this->application_id;
    }

    /**
     * Gets or sets the application_secret
     * @return client_credentials|string 
     */
    public function application_secret(string $application_secret)
    {
        if (null !== $application_secret) {
            $this->application_secret = $application_secret;
            return $this;
        }
        return $this->application_secret;
    }
}
