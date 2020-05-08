<?php

/**
 * Note: this is Production!
 */

global $DC_PROJECT;
global $CIN_PROJECT;
global $RTP_PROJECT;
global $BASE_URL;
global $REST_URL;
global $CREATE_ISSUE_URL;
global $USERNAME;
global $SERVER;
global $SUBMIT_MESSAGE;
global $OPTIONS;
global $DOMESTIC_PROJECT;
global $INTERNATIONAL_PROJECT;
global $VOUCHERS_PROJECT;

$DC_PROJECT = '10332';
$CIN_PROJECT = '10331';
$RTP_PROJECT =  '10333';

$DOMESTIC_PROJECT = '11533';
$INTERNATIONAL_PROJECT = '11534';
$VOUCHERS_PROJECT = '11630';

$BASE_URL = "https://jira.ord.epa.gov";

$REST_URL = $BASE_URL."/rest/api/2";
$CREATE_ISSUE_URL = $REST_URL."/issue/";

$USERNAME = "travel-forms:t0Mangen";

$SERVER = '';

$SUBMIT_MESSAGE = 'Processing Submission, please wait.';

//These are the options for the LCO Dropdown
$OPTIONS = array(
    "IO","BCTD","CCED","GLTED","SCDCD",
    "IO","AMCD","AESMD","ACESD","GEMMD","WECD","EPD",
    "IO","TSCD","GCRD","HSMMD","LRTD","WID",
    "IO","CPAD","HEEAD","PHITD","PESD","PHESD",
    "IO","AE","CSS","HSRP","HERA","SHC","SSWR",
    "IO","IAD","HCD","PBD","EMD","RSCD",
    "IO","ERPD","SPD",
    "IO","CISD","AIMD",
);
// @see http://stackoverflow.com/questions/6175548/php-array-copy-values-to-keys
$OPTIONS = array_combine($OPTIONS, $OPTIONS);
?>
