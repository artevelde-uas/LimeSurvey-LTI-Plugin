<?php

use IMSGlobal\LTI\OAuth\OAuthDataStore;
use IMSGlobal\LTI\OAuth\OAuthConsumer;
use IMSGlobal\LTI\OAuth\OAuthToken;


/**
 * A Trivial memory-based store - no support for tokens
 */
class ArrayOAuthDataStore extends OAuthDataStore
{
    private $consumers = [];

    function add_consumer($consumer_key, $consumer_secret)
    {
        $this->consumers[$consumer_key] = $consumer_secret;
    }

    function lookup_consumer($consumer_key)
    {
        if ($this->consumers[$consumer_key]) {
            return new OAuthConsumer($consumer_key, $this->consumers[$consumer_key]);
        }

        return null;
    }

    function lookup_token($consumer, $token_type, $token)
    {
        return new OAuthToken($consumer, '');
    }
}
