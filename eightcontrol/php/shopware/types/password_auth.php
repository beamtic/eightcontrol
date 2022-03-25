<?php

/**
 *     OAuth 2.0 Password authentication type
 */

namespace eightcontrol\shopware\types;

use Exception;

/**
 * OAuth 2.0 auth type for using the admin-api with username + password. Rember to use an admin account for this.
 * @package eightcontrol\shopware\types
 */
class password_auth extends auth_abstract_base
{
    private string $username;
    private string $password;


    /**
     * Attempt to obtain a new Oauth token. Note. Using token() will totally automate obtaining, reusing, and renewing tokens
     * @return api_token 
     * @throws Exception 
     */
    public function oauth_obtain_token(): api_token
    {
        if ((!isset($this->username)) || (!isset($this->password))) {
            new Exception('Please provide a username and password before trying to authenticate with the password grant type.');
        }

        $response = $this->http->post(
            $this->d->scheme() . '://' . $this->d->host() . $this->d->oauth_token_endpoint(),
            null,
            $this->d->default_headers(),
            json_encode([
                "client_id" => "administration",
                "grant_type" => "password",
                "scopes" => "write",
                "username" => $this->username,
                "password" => $this->password
            ])
        );

        return $this->save_token($response->body_entity());
    }

    /**
     * Returns the username if defined, password is private
     * @return password_auth|string|null
     */
    public function username(string $username = null): password_auth|string
    {
        if (null !== $username) {
            $this->username = $username;
            $this->oauth_tmp_file_path = $this->d->base_path() . 'tmp/shopware/' . $this->username . '.json';
            return $this;
        }
        return $this->username;
    }

    /**
     * Returns "private" if password is defined, null otherwise
     * @return password_auth|string
     */
    public function password(string $password = null): password_auth|string
    {
        if (null !== $password) {
            $this->password = $password;
            return $this;
        }
        return 'private';
    }
}
