<?php
/**
 * Make LimeSurvey an LTI provider
 * Plugin based on "zesthook" by Evently-nl
 *
 * @author Adam Zammit <adam@acspri.org.au>
 * @copyright 2018,2020 ACSPRI <https://www.acspri.org.au>
 * @author Stefan Verweij <stefan@evently.nl>
 * @copyright 2016 Evently <https://www.evently.nl>
 * @license GPL v3
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */
class LTIPlugin extends PluginBase {

    protected $storage = 'DbStorage';
    static protected $description = 'Make LimeSurvey an LTI provider';
    static protected $name = 'LTIPlugin';

    public function init() {
        $this->subscribe('beforeSurveySettings');
        $this->subscribe('newSurveySettings');
        $this->subscribe('newDirectRequest'); //for LTI call
        $this->subscribe('newUnsecureRequest','newDirectRequest'); //for LTI call
    }

    protected $settings = array(
        'sResourceIdAttribute' => array (
            'type' => 'string',
            'default' => 'resource_link_id',
            'label' => 'REQUIRED: The LTI attributes that stores the unique Resource ID - this is how the LTI system identifies the resources that contains the LTI Consumer (eg the Unit)',
            'help' => 'For openEdX it is probably resource_link_id, for Canvas it is probably custom_canvas_course_id. This maps to ATTRIBUTE_3 in your participant table'
        ),
        'sUserIdAttribute' => array (
            'type' => 'string',
            'default' => 'user_id',
            'label' => 'REQUIRED: The LTI attributes that stores the unique User ID',
            'help' => 'For openEdX it is probably user_id, for Canvas it is probably custom_canvas_user_id. This maps to ATTRIBUTE_4 in your participant table'
        ),
        'sUrlAttribute' => array (
            'type' => 'string',
            'default' => 'launch_presentation_return_url',
            'label' => 'Optional: The LTI attributes that stores the return URL',
            'help' => 'Leave blank for no data to be stored. For Canvas it appears to be launch_presentation_return_url. This maps to ATTRIBUTE_1 in your participant table'
        ),
        'sCourseTitleAttribute' => array (
            'type' => 'string',
            'default' => 'context_title',
            'label' => 'Optional: The LTI attributes that stores the course title',
            'help' => 'Leave blank for no data to be stored. For openEdX and Canvas it appears to be context_title. This maps to ATTRIBUTE_2 in your participant table'
        ),
        'sEmailAttribute' => array (
            'type' => 'string',
            'default' => 'lis_person_contact_email_primary',
            'label' => 'Optional: The LTI attributes that stores the participants email address',
            'help' => 'Leave blank for no data to be stored. For openEdX and Canvas it appears to be lis_person_contact_email_primary. This maps to email in your participant table'
        ),
        'sFirstNameAttribute' => array (
            'type' => 'string',
            'default' => 'lis_person_name_given',
            'label' => 'Optional: The LTI attributes that stores the first name of the participant',
            'help' => 'Leave blank for no data to be stored. For openEdX and Canvas it appears to be lis_person_name_given. This maps to firstname in your participant table'
        ),
        'sLastNameAttribute' => array (
            'type' => 'string',
            'default' => 'lis_person_name_family',
            'label' => 'Optional: The LTI attributes that stores the last name of the participant',
            'help' => 'Leave blank for no data to be stored. For openEdX and Canvas it appears to be lis_person_name_family. This maps to lastname in your participant table'
        ),
        'bDebugMode' => array (
            'type' => 'select',
            'options' => array (
                0 => 'No',
                1 => 'Yes'
            ),
            'default' => 0,
            'label' => 'Enable Debug Mode',
            'help' => 'Enable debugmode to see what data is transmitted'
        )
    );


    /** Adapted from: https://github.com/SondagesPro/LS-extendRemoteControl/blob/master/extendRemoteControl.php
     */
    public function newDirectRequest()
    {
        $oEvent = $this->getEvent();
        if ($oEvent->get('target') != $this->getName())
            return;
        $action = $oEvent->get('function');

        if (!empty($action)) {
            $iSurveyId = intval($action);

            $surveyidExists = Survey::model()->findByPk($iSurveyId);
            if (!isset($surveyidExists)) {
                die("Survey $iSurveyId does not exist");
            }

            require_once(dirname(__FILE__) . '/include/ims-blti/blti.php');

            //Build the LTI object with the credentials as we know them
            $context = new BLTI($this->get('sAuthSecret','Survey', $iSurveyId), false, false);

            //Check if the correct key is being sent
            if ($context->info['oauth_consumer_key'] == $this->get('sAuthKey','Survey', $iSurveyId)){
                //Make sure our LTI object's OAuth connection is valid
                if ($context->valid ){
                    $this->debug("Valid LTI Connection",$context->info,microtime(true));

                    if (!tableExists("{{tokens_$iSurveyId}}")) {
                        die("No participant table for survey $iSurveyId");
                    }

                    //store the return url somewhere if it exists
                    $urlAttribute = $this->get('sUrlAttribute',null,null,$this->settings['sUrlAttribute']);
                    $url = "";
                    if (!empty($urlAttribute) && isset($context->info[$urlAttribute])) {
                        $url = $context->info[$urlAttribute];
                    }

                    //If we want to limit completion to one per course/user combination:
                    $bMultipleCompletions = $this->get('bMultipleCompletions','Survey', $iSurveyId);

                    $token_count = 0;

                    $token_query = array('attribute_3' => $context->info[$this->get('sResourceIdAttribute',null,null,$this->settings['sResourceIdAttribute'])],
                        'attribute_4' => $context->info[$this->get('sUserIdAttribute',null,null,$this->settings['sUserIdAttribute'])]);

                    if ($bMultipleCompletions != 1) {
                        //search for token based on attribute_3 and attribute_4 (resource id and user id)
                        $token_count = Token::model($iSurveyId)->countByAttributes($token_query);

                    }

                    if ($bMultipleCompletions == 1 || $token_count == 0) { //if no token, then create a new one and start survey
                        $firstname = isset($context->info[$this->get('sFirstNameAttribute',null,null,$this->settings['sFirstNameAttribute'])])?$context->info[$this->get('sFirstNameAttribute',null,null,$this->settings['sFirstNameAttribute'])]:"";
                        $lastname = isset($context->info[$this->get('sLastNameAttribute',null,null,$this->settings['sLastNameAttribute'])])?$context->info[$this->get('sLastNameAttribute',null,null,$this->settings['sLastNameAttribute'])]:"";
                        $email = isset($context->info[$this->get('sEmailAttribute',null,null,$this->settings['sEmailAttribute'])])?$context->info[$this->get('sEmailAttribute',null,null,$this->settings['sEmailAttribute'])]:"";

                        $attribute_2 = isset($context->info[$this->get('sCourseTitleAttribute',null,null,$this->settings['sCourseTitleAttribute'])])?$context->info[$this->get('sCourseTitleAttribute',null,null,$this->settings['sCourseTitleAttribute'])]:"";
                        $token_add = array('attribute_1' => $url,
                            'attribute_2' => $attribute_2,
                            'firstname' => $firstname,
                            'lastname' => $lastname,
                            'email' => $email);
                        $token = Token::create($iSurveyId);
                        $token->setAttributes(array_merge($token_query,$token_add));
                        $token->generateToken();
                        if ($token->save()) {
                            Yii::app()->getController()->redirect(Yii::app()->createAbsoluteUrl('survey/index', array('sid' => $iSurveyId, 'token' => $token->token, 'newtest' => 'Y')));
                        } else {
                            die("Error creating token");
                        }
                    } else { //else if a token continue where left off
                        $token = Token::model($iSurveyId)->findByAttributes($token_query);
                        //already completed.
                        if ($token->completed != 'N') {
                            //display already completed and return to CANVAS
                            print "<p>Survey already completed</p>";
                        } else {
                            Yii::app()->getController()->redirect(Yii::app()->createAbsoluteUrl('survey/index', array('sid' => $iSurveyId, 'token' => $token->token)));
                        }
                    }
                } else  {
                    echo "Bad OAuth. Probably sent the wrong secret";
                }
            } else {
                echo "Wrong key passed";
            }
        } else {
            echo "No survey id passed";
        }
    }


    /**
     * Add setting on survey level: provide URL for LTI connector and check that tokens table / attributes exist
     */
    public function beforeSurveySettings()
    {
        $oEvent = $this->event;

        $survey = Survey::model()->findByPk($oEvent->get('survey'));

        $message = "";

        if (!tableExists($survey->responsesTableName)) {
            $message = "Please activate the survey before continuing";
        }

        if (!(isset($survey->tokenAttributes['attribute_1']) &&
            isset($survey->tokenAttributes['attribute_2']) &&
            isset($survey->tokenAttributes['attribute_3']) &&
            isset($survey->tokenAttributes['attribute_4'])) ) {
            $message = "Please ensure the survey participant function has been enabled, and that there at least 4 attributes created";
        }

        $apiKey = $this->get ( 'sAuthKey', 'Survey', $oEvent->get ( 'survey' ) );
        if (empty($apiKey) || trim($apiKey) == "") {
            $message = "Set an Auth key and save these settings before you can access the LTI URL";
        }

        $apiSecret = $this->get ( 'sAuthSecret', 'Survey', $oEvent->get ( 'survey' ) );
        if (empty($apiKey) || trim($apiSecret) == "") {
            $message = "Set an Auth secret and save these settings before you can access the LTI URL";
        }

        $kmessage = $message;

        if ($message == "") {
            $message =  Yii::app()->createAbsoluteUrl('plugins/direct', array('plugin' => "LTIPlugin", 'function' => $oEvent->get('survey'),));
            $kmessage = '"Advanced Module List" in "Advanced Settings" contains: ["lti_consumer"] and "LTI_Passports" contains: ["limesurvey:'.$apiKey.':'.$apiSecret.'"]';
        }

        $aSets = array (
            'sAuthKey' => array (
                'type' => 'string',
                'label' => 'REQUIRED: The key used as a password in your LTI system',
                'help' => 'Please use something random',
                'current' => $this->get('sAuthKey', 'Survey', $oEvent->get('survey'),$this->get('sAuthKey',null,null,str_replace(array('~', '_', ':'), array('a', 'z', 'e'), Yii::app()->securityManager->generateRandomString(32)))),
            ),
            'sAuthSecret' => array (
                'type' => 'string',
                'label' => 'REQUIRED: The secret used as a password in your LTI system',
                'help' => 'Please use something random',
                'current' => $this->get('sAuthSecret', 'Survey', $oEvent->get('survey'),$this->get('sAuthSecret',null,null,str_replace(array('~', '_',':'), array('a', 'z', 'e'), Yii::app()->securityManager->generateRandomString(32)))),
            ),
            'bMultipleCompletions' => array (
                'type' => 'select',
                'options' => array (
                    0 => 'No',
                    1 => 'Yes'
                ),
                'current' => $this->get('bMultipleCompletions', 'Survey', $oEvent->get('survey')),
                'label' => 'Allow a user in a course to complete this survey more than once',
                'help' => 'This will allow multiple tokens to be created for the same user each time they go to access the survey'
            ),

            'sInfo' => array (
                'type' => 'info',
                'label' => 'The URL to access this survey via the LTI Provider',
                'help' =>  $message
            ),
            'sInfo2' => array (
                'type' => 'info',
                'label' => 'If using OpenEdX ensure the following: ',
                'help' =>  $kmessage
            ),

        );

        $aSettings = array(
            'name' => get_class ( $this ),
            'settings' => $aSets,
        );
        $oEvent->set("surveysettings.{$this->id}", $aSettings);
    }

    /**
     * Save the settings
     */
    public function newSurveySettings()
    {
        $event = $this->event;
        foreach ($event->get('settings') as $name => $value)
        {
            /* In order use survey setting, if not set, use global, if not set use default */
            $default=$event->get($name,null,null,isset($this->settings[$name]['default'])?$this->settings[$name]['default']:NULL);
            $this->set($name, $value, 'Survey', $event->get('survey'),$default);
        }
    }


    private function debug($parameters, $hookSent, $time_start)
    {
        if($this->get('bDebugMode',null,null,$this->settings['bDebugMode'])==1)
        {
            echo '<pre>';
            var_dump($parameters);
            echo "<br><br> ----------------------------- <br><br>";
            var_dump($hookSent);
            echo "<br><br> ----------------------------- <br><br>";
            echo 'Total execution time in seconds: ' . (microtime(true) - $time_start);
            echo '</pre>';
        }
    }

}
