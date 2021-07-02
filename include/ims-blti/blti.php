<?php

use IMSGlobal\LTI\OAuth\OAuthServer;
use IMSGlobal\LTI\OAuth\OAuthSignatureMethod_HMAC_SHA1;
use IMSGlobal\LTI\OAuth\OAuthRequest;
Use TrivialOAuthDataStore;


// Returns true if this is a Basic LTI message
// with minimum values to meet the protocol
function is_basic_lti_request() {
   $good_message_type = $_REQUEST["lti_message_type"] == "basic-lti-launch-request";
   $good_lti_version = $_REQUEST["lti_version"] == "LTI-1p0";
   $resource_link_id = $_REQUEST["resource_link_id"];
   if ($good_message_type and $good_lti_version and isset($resource_link_id) ) return(true);
   return false;
}

// Basic LTI Class that does the setup and provides utility
// functions
class BLTI {

    public $complete = false;
    public $basestring = false;
    public $info = false;
    public $row = false;
    public $context_id = false;  // Override context_id

    function __construct($secret) {

        // If this request is not an LTI Launch, give up
        if ( ! is_basic_lti_request() ) return;

        // Insure we have a valid launch
        if ( empty($_REQUEST["oauth_consumer_key"]) ) {
            throw new Exception("Missing oauth_consumer_key in request");
        }

        $oauth_consumer_key = $_REQUEST["oauth_consumer_key"];

        // Verify the message signature
        $store = new TrivialOAuthDataStore();
        $store->add_consumer($oauth_consumer_key, $secret);

        $server = new OAuthServer($store);

        $method = new OAuthSignatureMethod_HMAC_SHA1();
        $server->add_signature_method($method);
        $request = OAuthRequest::from_request();

        $this->basestring = $request->get_signature_base_string();

            $server->verify_request($request);

        // Store the launch information in the session for later
        $newinfo = array();
        foreach($_POST as $key => $value ) {
            if ( $key == "basiclti_submit" ) continue;
            if ( strpos($key, "oauth_") === false ) {
                $newinfo[$key] = $value;
                continue;
            }
            if ( $key == "oauth_consumer_key" ) {
                $newinfo[$key] = $value;
                continue;
            }
        }

        $this->info = $newinfo;
    }

}

?>
