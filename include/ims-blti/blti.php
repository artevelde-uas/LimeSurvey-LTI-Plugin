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

    public $valid = false;
    public $complete = false;
    public $message = false;
    public $basestring = false;
    public $info = false;
    public $row = false;
    public $context_id = false;  // Override context_id

    function __construct($secret) {

        // If this request is not an LTI Launch, give up
        if ( ! is_basic_lti_request() ) return;

        // Insure we have a valid launch
        if ( empty($_REQUEST["oauth_consumer_key"]) ) {
            $this->message = "Missing oauth_consumer_key in request";
            return;
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

        try {
            $server->verify_request($request);
            $this->valid = true;
        } catch (Exception $e) {
            $this->message = $e->getMessage();
            return;
        }

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

    function addSession($location) {
        if ( ini_get('session.use_cookies') == 0 ) {
            if ( strpos($location,'?') > 0 ) {
               $location = $location . '&';
            } else {
               $location = $location . '?';
            }
            $location = $location . session_name() . '=' . session_id();
        }
        return $location;
    }

    function isInstructor() {
        $roles = $this->info['roles'];
        $roles = strtolower($roles);
        if ( ! ( strpos($roles,"instructor") === false ) ) return true;
        if ( ! ( strpos($roles,"administrator") === false ) ) return true;
        return false;
    }


    function getResourceKey() {
        $oauth = $this->info['oauth_consumer_key'];
        $id = $this->info['resource_link_id'];
        if ( strlen($id) > 0 and strlen($oauth) > 0 ) return $oauth . ':' . $id;
        return false;
    }

    function getResourceTitle() {
        $title = $this->info['resource_link_title'];
        if ( strlen($title) > 0 ) return $title;
        return false;
    }

    // TODO: Add javasript version if headers are already sent
    function redirect() {
            $host = $_SERVER['HTTP_HOST'];
            $uri = $_SERVER['PHP_SELF'];
            $location = $_SERVER['HTTPS'] ? 'https://' : 'http://';
            $location = $location . $host . $uri;
            $location = $this->addSession($location);
            header("Location: $location");
    }

    function dump() {
        if ( ! $this->valid or $this->info == false ) return "Context not valid\n";
        $ret = "";
        $ret .= "getResourceKey() = ".$this->getResourceKey()."\n";
        $ret .= "getResourceTitle() = ".$this->getResourceTitle()."\n";
        return $ret;
    }

}

?>
