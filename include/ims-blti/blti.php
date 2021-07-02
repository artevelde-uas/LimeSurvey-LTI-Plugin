<?php

use IMSGlobal\LTI\OAuth\OAuthServer;
use IMSGlobal\LTI\OAuth\OAuthSignatureMethod_HMAC_SHA1;
use IMSGlobal\LTI\OAuth\OAuthRequest;
Use TrivialOAuthDataStore;


// Returns true if this is a Basic LTI message
// with minimum values to meet the protocol
function is_basic_lti_request() {
}

// Basic LTI Class that does the setup and provides utility
// functions
class BLTI {

    public $info = false;

    function __construct($secret) {

        // If this request is not an LTI Launch, give up
        if ( ( $_REQUEST["lti_message_type"] != "basic-lti-launch-request" ) || ( $_REQUEST["lti_version"] !== "LTI-1p0" ) ) {
            throw new Exception("Not a valid LTI launch request");
        }

        if ( !isset($_REQUEST["resource_link_id"]) ) {
            throw new Exception("No resource link id provided");
        }

        // Insure we have a valid launch
        if ( empty($_REQUEST["oauth_consumer_key"]) ) {
            throw new Exception("Missing oauth_consumer_key in request");
        }

        // Verify the message signature
        $store = new TrivialOAuthDataStore();
        $store->add_consumer($_REQUEST["oauth_consumer_key"], $secret);

        $server = new OAuthServer($store);

        $method = new OAuthSignatureMethod_HMAC_SHA1();
        $server->add_signature_method($method);
        $request = OAuthRequest::from_request();

        $server->verify_request($request);

        // Store the launch information in the session for later
        $info = array();
        foreach($_POST as $key => $value ) {
            if ( $key == "basiclti_submit" ) continue;
            if ( strpos($key, "oauth_") === false ) {
                $info[$key] = $value;
                continue;
            }
            if ( $key == "oauth_consumer_key" ) {
                $info[$key] = $value;
                continue;
            }
        }

        $this->info = $info;
    }

}

?>
