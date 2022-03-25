<?php

/**
 *   Abstract base-class for creating new authentication types for OAuth
 */

namespace eightcontrol\shopware\types;

use eightcontrol\file_handler\file_handler;
use eightcontrol\http_client\http_client;
use eightcontrol\shopware\types\site_detais;
use Exception;

abstract class auth_abstract_base
{
    // Locally stored token
    private api_token $token;
    protected $oauth_token_endpoint = '/api/oauth/token';
    protected $oauth_tmp_file_path;

    protected http_client $http;
    protected file_handler $fh;
    protected site_details $d;

    public function __construct(http_client $http, file_handler $file_handler, site_details $site_details)
    {
        $this->http = $http;
        $this->fh = $file_handler;
        $this->d = $site_details;
    }


    /**
     * Attempt to obtain a new Oauth token. Note. Using token() will totally automate obtaining, reusing, and renewing tokens
     * @return api_token
     * @throws Exception
     */
    abstract protected function oauth_obtain_token(): api_token;

    /**
     * Attempt to renew existing token. Note. Using token() will totally automate obtaining, reusing, and renewing tokens
     * @return api_token
     * @throws Exception
     */
    protected function oauth_renew_token(): api_token
    {
        // Attempt to obtain fresh token using refresh_token
        $response = $this->http->post(
            $this->d->scheme() . '://' . $this->d->host() . $this->oauth_token_endpoint,
            null,
            $this->d->default_headers(),
            json_encode([
                "client_id" => "administration",
                "grant_type" => "refresh_token",
                "refresh_token" => $this->token->refresh_token(),
                "scopes" => "write"
            ])
        );

        // Attempt to save the obtained token, throw exception on error
        return $this->save_token($response->body_entity());
    }

    /**
     * @param string $json
     * @return api_token
     * @throws Exception
     */
    protected function save_token(string $json): api_token
    {
        // Decoding the JSON data yields a stdClass object that can be accessed like this: $objectVarName->access_token
        // We must store the relevant parts either as a Cookie (JavaScript) or if using PHP, as a server-sided file or session cookie (PHP)
        // If storing as a server-sided file, we can store the data based on the username. E.g.: file_put_contents('/path/to/temp/file', md5($username + hash))
        // Alternatively, using sessions has pretty much the same effect, except that they will expire by default; but, this does not matter, since the tokens themselves will also expire.
        // However, for server-sided scripts, session cookies will not work unless we store the session ID somewhere, therefor we might as well make our own system for this stuff.
        // Attempt to decode the json from the refresh_token request
        if (($json_decoded = json_decode($json)) === null) {
            throw new Exception("Unable to decode file contents using json_decode: " . $this->oauth_tmp_file_path . $json_decoded);
        }

        $json_decoded->timestamp_unix = time();
        $json_decoded->expires_at_unix = $json_decoded->timestamp_unix + $json_decoded->expires_in;
        $json_decoded->refresh_token = (isset($json_decoded->refresh_token)) ? $json_decoded->refresh_token : '';

        // Remember parameters in $this
        $this->token = new api_token(
            $json_decoded->token_type,
            $json_decoded->expires_in,
            $json_decoded->access_token,
            $json_decoded->refresh_token,
            $json_decoded->timestamp_unix,
            $json_decoded->expires_at_unix
        );

        // Store the data in a temp file after adding a timestamp (overwrites old token data on refresh requests)
        $this->fh->write_file($this->oauth_tmp_file_path, json_encode($json_decoded));

        return $this->token;
    }

    /**
     * Reads a Token file and saves its content into an apiToken object: $this->token
     * @return api_token
     * @throws Exception
     */
    private function read_token_file(): api_token
    {
        // If we do not have any locally stored parameters try to read the parameters from the token file
        $json = $this->fh->read_file_lines($this->oauth_tmp_file_path);

        if (($json_decoded = json_decode($json)) === null) {
            throw new Exception("Unable to decode file contents using json_decode: " . $this->oauth_tmp_file_path . $json_decoded);
        }

        if (!isset($json_decoded->expires_at_unix)) {
            if (!unlink($this->oauth_tmp_file_path)) {
                throw new Exception('Unable to delete file: ' . $this->oauth_tmp_file_path);
            }
            throw new Exception('It looks like the token file is either corrupt, or we did not obtain the expected parameters from the API. The file should now be deleted.');
        }
        // Remember parameters in $this
        return new api_token(
            $json_decoded->token_type,
            $json_decoded->expires_in,
            $json_decoded->access_token,
            $json_decoded->refresh_token,
            $json_decoded->timestamp_unix,
            $json_decoded->expires_at_unix
        );
    }

    /**
     * Automatically obtain, reuse, and renew the OAuth token.
     * @return api_token
     * @throws Exception
     */
    public function token(): api_token
    {
        // Check if we need to obtain a new token
        // If a token file does not already exist, assume we need to obtain a new token
        if (!file_exists($this->oauth_tmp_file_path)) {
            // If a valid token does not exist, attempt to obtain a new OAuth token
            return $this->oauth_obtain_token();
        } else {
            // If a token file did exist, check if we have local variables (expected)
            if (isset($this->token)) {
                // Try using the locally stored parameters
                // Check if Token has expired
                if ($this->token->expires_at_unix() < time()) {
                    if ('' !== $this->token->refresh_token()) {
                        return $this->oauth_renew_token();
                    } else {
                        return $this->oauth_obtain_token();
                    }
                } else {
                    return $this->token;
                }
            } else {
                // Read the Stored token from disk
                $this->token = $this->read_token_file();
            }
        }

        // Check if token is expired
        if ($this->token->expires_at_unix() < time()) {
            if ('' !== $this->token->refresh_token()) {
                return $this->oauth_renew_token();
            } else {
                return $this->oauth_obtain_token();
            }
        } else {
            return $this->token;
        }
    }
}
