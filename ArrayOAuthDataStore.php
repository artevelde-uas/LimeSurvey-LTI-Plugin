<?php

use IMSGlobal\LTI\OAuth\OAuthDataStore;
use IMSGlobal\LTI\OAuth\OAuthConsumer;
use IMSGlobal\LTI\OAuth\OAuthToken;


/**
 * A Trivial memory-based store - no support for tokens
 */
class ArrayOAuthDataStore extends OAuthDataStore
{
    private $consumers = array();

    function add_consumer($consumer_key, $consumer_secret)
    {
        $this->consumers[$consumer_key] = $consumer_secret;
    }

    function lookup_consumer($consumer_key)
    {
        if ($this->consumers[$consumer_key]) {
            return new OAuthConsumer($consumer_key, $this->consumers[$consumer_key], null);
        }

        return null;
    }

    function lookup_token($consumer, $token_type, $token)
    {
        return new OAuthToken($consumer, "");
    }

    // Return null if the nonce has not been used
    // Return $nonce if the nonce was previously used
    function lookup_nonce($consumer, $token, $nonce, $timestamp)
    {
        // Should add some clever logic to keep nonces from
        // being reused - for no we are really trusting
        // that the timestamp will save us
        return null;
    }

    function new_request_token($consumer)
    {
        return null;
    }

    function new_access_token($token, $consumer)
    {
        return null;
    }
}
