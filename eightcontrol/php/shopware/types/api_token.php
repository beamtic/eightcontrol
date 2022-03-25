<?php
/**
 *
 * Used to store parameters obtained from the API
 *
 * @Author Jacob (JacobSeated)
 */
namespace eightcontrol\shopware\types;

class api_token
{

    // From Shopware's API
    private string $token_type;
    private int $expires_in;
    private string $access_token;
    private string $refresh_token;

    // Custom properties
    private int $timestamp_unix;
    private int $expires_at_unix;

    public function __construct($token_type, $expires_in, $access_token, $refresh_token, $timestamp_unix, $expires_at_unix)
    {
        $this->token_type = $token_type;
        $this->expires_in = $expires_in;
        $this->access_token = $access_token;
        $this->refresh_token = $refresh_token;
        $this->timestamp_unix = $timestamp_unix;
        $this->expires_at_unix = $expires_at_unix;
    }

    /**
     * The type of the token

     * @return string
     */
    public function token_type(): string
    {
        return $this->token_type;
    }

    /**
     * Number of seconds until the token expires from timestamp()
     * @return string
     */
    public function expires_in(): string
    {
        return $this->expires_in;
    }

    /**
     * The access_token is used when performing API requests
     * @return string
     */
    public function access_token(): string
    {
        return $this->access_token;
    }

    /**
     * The refresh_token is used to renew an expired token
     * @return string
     */
    public function refresh_token(): string
    {
        return $this->refresh_token;
    }

    /**
     * Timestamp of when the token was obtained or renewed
     * @return int
     */
    public function timestamp_unix() : int {
        return $this->timestamp_unix;
    }

    /**
     * Timestamp of when the Token is set to expire
     * @return int
     */
    public function expires_at_unix() : int {
        return $this->expires_at_unix;
    }
}