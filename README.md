# LTIPlugin
LimeSurvey Plugin that allows LimeSurvey to act as an LTI provider for tools such as Canvas and openEdX. LimeSurvey will have access to the LMS course name and course and student identifier and allow the completion of a survey.

## Installation

Download the zip from the [releases](https://github.com/adamzammit/LTIPlugin/releases) page and extract to your plugins folder. You can also clone directly from git: go to your plugins directory and type
```
git clone https://github.com/adamzammit/LTIPlugin.git LTIPlugin
```

## Requirements

- LimeSurvey version 3.x, 4.x
- Surveys need to be activated, with a participant table set up with at least 4 attributes avaiable (the plugin will use the first 4 attributes for LTI related data)

## Configuration (LimeSurvey)

1. Visit the "Plugin manager" section in your LimeSurvey installation under "Configuration"
2. Confirm the LTI attributes match the system you wish to use (examples are given for OpenEdX and Canvas, also you can use Debug mode if you want to discover these yourself for testing in your own system)
3. Save the settings
4. Activate the plugin
5. Activate an existing or new survey
6. Visit "Simple plugin settings" for the survey and choose "Settings for plugin LTIPlugin"
7. A random key and password should be generated - save the settings then a URL to access should be displayed (otherwise a message will be displayed notifying of the requirements for the LTI plugin as above)
8. Use the URL listed and the key and secret generated to set up your LMS to use LimeSUrvey as an LTI Provider (see below for examples)
9. By default a course participant will be able to complete the survey only once, and will return to the previous point of completion when visiting the survey again if not completed. If you want them to be able to complete multiple times for the same unit - please set "Allow a user in a course to complete this survey more than once" to "Yes"

### Configuration (OpenEdX)

1. Edit your course in OpenEdX "Studio"
2. In your course, visit "Settings" then "Advanced Settings"
3. Ensure "Advanced Module List" contains:
```
    ["lti_consumer"]
```
4. Ensure "LTI Passports" contains:
```
    ["limesurvey:KEY:SECRET"]
```
   (Where KEY and SECRET are replaced with the key and secret generated in the configuration step above - this will also be able to be copied and pasted from the LTIPlugin settings in LimeSurvey)
5. Save the Advanced Settings

If you have recieved a "CSRF Token" error in LimeSurvey you may need to set "LTI Launch Target" to "New Window" in OpenEdX to overcome this.

### Usage (OpenEdX)

1. Add a new "Unit"
2. Choose "Advanced" as the Component (if "Advanced" doesn't appear, check your Configuration settings as above)
3. Click on "LTI Consumer"
4. Click on "Edit"
5. Enter a display name - this can be anything you choose
6. The "LTI ID" should be:
```
    limesurvey
```
7. The "LTI URL" is the URL that appears on the "Settings for plugin LTI Plugin" page for your survey
8. Other settings can remain as default
9. Click "Save" and you will now be able to access LimeSurvey from within

## Security

If you discover any security related issues, please email adam@acspri.org.au instead of using the issue tracker.

## Contributing

PR's are welcome!

## Usage

You are free to use/change/fork this code for your own products (licence is GPLv3), and I would be happy to hear how and what you are using it for!
