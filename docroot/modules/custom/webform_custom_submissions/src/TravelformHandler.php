<?php

namespace Drupal\webform_custom_submissions;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use \Exception;

require_once('../travel_services_config.inc');

class TravelformHandler {

  protected $create_issue_url;
  protected $domestic_project;
  protected $international_project;
  protected $submission_client;
  protected $username;
  protected $vouchers_project;

  public function __construct() {
    global $CREATE_ISSUE_URL;
    global $DOMESTIC_PROJECT;
    global $INTERNATIONAL_PROJECT;
    global $USERNAME;
    global $VOUCHERS_PROJECT;

    $this->create_issue_url = $CREATE_ISSUE_URL;
    $this->domestic_project = $DOMESTIC_PROJECT;
    $this->international_project = $INTERNATIONAL_PROJECT;
    $this->submission_client = new Client(['base_uri' => $CREATE_ISSUE_URL]);
    $this->username = $USERNAME;
    $this->vouchers_project = $VOUCHERS_PROJECT;
  }

  /*
   * Script for getting, formatting and submitting travel services form data to JIRA
   */

  function submitToJira($form, &$form_state) {
    try {
      $postData = $this->compilePOSTData();
      $postResponse = $this->sendPOSTData($postData);

      //Uncomment to show full repsonse in broswer
      //drupal_set_message(json_decode(var_export($postResponse, true)));
      //var_export($_POST,true);

      //dpm($_POST);
      //drupal_set_message($postData, 'error');
      //drupal_set_message($postResponse, 'error');
      $decoded = json_decode($postResponse, TRUE);
      if (isset($decoded['id'])) {
        $page = explode('/', \Drupal::service('path.current')->getPath());
        \Drupal::logger('Travel Services Response ' . $page[sizeof($page) - 1])->notice($postResponse);
        $issueId = $this->getIssueId($postResponse);

        $filesUploaded = $this->attachFiles($issueId, $form_state);
        $this->parseIssueResponse($postResponse, $filesUploaded, $_POST['formTitle'], $postData, $_FILES);
      } elseif (isset($decoded['errorMessages'])) {
        \Drupal::logger('Travel Services Error')->notice($postResponse);
        drupal_set_message(t('There was an error processing your request. Code-0001'), 'error');
        mail('zerihun.tegegn@cgifederal.com', 'TravelServices Error', $postResponse);
      } else {
        drupal_set_message(t('There was an error processing your request. Code-0002'), 'error');
        \Drupal::logger('Travel Services Error')->notice('Unidentified Error: JIRA Response = ' . $postResponse);
      }
    } catch (Exception $e) {
      \Drupal::logger('Travel Services Exception')->notice($e->getMessage());
      drupal_set_message(t('Unable to process request at this time, please try again later.'), 'error');
      //drupal_set_message($e->getMessage());
    }
  }//end submitToJira

//Compiles POST data and returns the data formatted as JSON
  function compilePOSTData() {

    $dropDowns = array(
      'customfield_10093',
      'customfield_10098',
      'customfield_10112',
      'customfield_10120',
      'customfield_10140',
      'customfield_10190',
      'customfield_10191',
      'customfield_10193',
      'customfield_10199',
      'customfield_10246',
      'customfield_10262',
      'customfield_10263',
      'customfield_10269',
      'customfield_10277',
      'customfield_10294',
      'customfield_10297',
      'customfield_10302',
      'customfield_10304',
      'customfield_10315',
      'customfield_10316',
      'customfield_10318',
      'customfield_10320',
      'customfield_10322',
      'customfield_10324',
      'customfield_10423',
      'customfield_10424',
      'customfield_10426',
      'customfield_10427',
      'customfield_10428',
      'customfield_10431',
      'customfield_10435',
      'customfield_10501',
      'customfield_10922',
      'customfield_11424',
      'customfield_11428',
      'customfield_11826',
      'customfield_11827',
    );

    $data = array('fields' => array());

    //Add POST variables to the array
    foreach ($_POST as $key => $val) {
      // ignore file uploads, we are handling these later
      if (isset($val['fid'])) {
        continue;
      }

      if ($key == 'customfield_10191') {
        $data['fields'][$key] = array('value' => $val);
        switch ($val) {
          case 'Domestic':
            $data['fields']['project'] = array('id' => $this->domestic_project);
            break;
          case 'Invitational (Non-EPA)':
            $data['fields']['project'] = array('id' => $this->domestic_project);
            break;
          case 'International':
            $data['fields']['project'] = array('id' => $this->international_project);
            break;
        }
      } elseif ($key == 'formTitle') {
        if ($val == 'Traveler ID' || $val == 'Concur Routing' || $val == 'Travel Question' || $val == 'Traveler Profile') {
          $data['fields']['project'] = array('id' => $this->domestic_project);
        } else if ($val == 'Travel Voucher') {
          $data['fields']['project'] = array('id' => $this->vouchers_project);
        }
      } //Capture Traveler L/C/O and set Project ID
      elseif ($key == 'customfield_10093') {
        $data['fields'][$key] = array('value' => $val);

        /*switch ($val) {
          case 'NHSRC':
          case 'NRMRL':
            $data['fields']['project'] = array('id' => $CIN_PROJECT);
            break;
          case 'IOAA':
          case 'OPARM':
          case 'OSA':
          case 'OSP':
          case 'NCER':
          case 'NCEA':
            $data['fields']['project'] = array('id' => $DC_PROJECT);
            break;
          case 'NERL':
          case 'NCCT':
          case 'NHEERL':
          case 'OSIM':
          case 'OARS':
            $data['fields']['project'] = array('id' => $RTP_PROJECT);
            break;
        }*/
      } //Capture IssueType
      elseif ($key == 'issuetype') {
        $data['fields']['issuetype'] = array('id' => $val);
      } //Capture dropdowns and turn them into arrays
      elseif (in_array($key, $dropDowns)) {
        //ignore time dropdowns if no time is selected
        if (($key == 'customfield_10322' ||
            $key == 'customfield_10320' ||
            $key == 'customfield_10324' ||
            $key == 'customfield_10316' ||
            $key == 'customfield_10302' ||
            $key == 'customfield_10318') && $val == 'none'
        ) {
          //do nothing
        } else {
          $data['fields'][$key] = array('value' => $val);
        }
      } //Capture Checkboxes and turn them into arrays
      elseif ($key == 'customfield_10411' ||
        $key == 'customfield_10415' ||
        $key == 'customfield_10150' ||
        $key == 'customfield_10149' ||
        $key == 'customfield_10148' ||
        $key == 'customfield_10151' ||
        $key == 'customfield_10278' ||
        $key == 'customfield_10280' ||
        $key == 'customfield_10103'
      ) {
        $checkboxArray = array();
        foreach ($val as $field2 => $value2) {
          array_push($checkboxArray, array('value' => $value2));
        }
        $data['fields'][$key] = $checkboxArray;
      }
      //Drupal 7 FAPI #states property does not currently support 'OR'
      //This workaround allows us to hide, show elements on 'OR' - These are fields in Travel Authorization
      elseif ($key == 'customfield_10431a' ||
        $key == 'customfield_10431b' && $val != ''
      ) {
        $data['fields']['customfield_10431'] = array('value' => $val);
      }
      //Drupal 7 FAPI #states property does not currently support 'OR'
      //This workaround allows us to hide, show elements on 'OR' - These are fields in Travel Authorization
      elseif ($key == 'customfield_10432a' ||
        $key == 'customfield_10432b' && $val != ''
      ) {
        $data['fields']['customfield_10432'] = $val;
      }
      //Drupal 7 FAPI #states property does not currently support 'OR'
      //This workaround allows us to hide, show elements on 'OR' - These are fields in Travel Authorization
      elseif ($key == 'customfield_10105a' ||
        $key == 'customfield_10105b' ||
        $key == 'customfield_10105c' && $val != ''
      ) {
        $data['fields']['customfield_10105'] = $val;
      } //Ignore unwanted post variables
      elseif ($key == 'op' ||
        $key == 'form_build_id' ||
        $key == 'form_token' ||
        $key == 'form_id' ||
        $key == 'formTitle' ||
        $key == 'proxy' ||
        substr($key, -4) == 'date' ||
        $key == 'hotelPreference' ||
        $key == 'blockOfRooms' ||
        $key == 'multiDestinations' ||
        $key == 'showMealsSelect'
      ) {
      } //Ignore empty variables
      elseif ($val == '') {
      } //Capture the rest of the customfield POST variables
      else {
        $data['fields'][$key] = $val;
      }
    }//end foreach

    //Set the Summary field
    if ($_POST['proxy'] == 'Yes')
      $data['fields']['summary'] = $_POST['formTitle'] . ': ' . $data['fields']['customfield_10331'];
    else if ($_POST['proxy'] == 'No')
      $data['fields']['summary'] = $_POST['formTitle'] . ': ' . $data['fields']['customfield_10090'];

    if ($_POST['customfield_10431a'] == 'Yes' || $_POST['customfield_10431b'] == 'Yes')
      $data['fields']['customfield_10431'] = array('value' => 'Yes');

    $jsonData = json_encode($data);

    return $jsonData;
  }

//Builds and sends cURL request for the form POST data
  function sendPOSTData($jsonData) {
    try {
      $header = array(
        "Authorization: Basic " . $this->username,
        "Content-Type: application/json"
      );
      $curlSession = curl_init();
      curl_setopt($curlSession, CURLOPT_HTTPHEADER, $header);
      curl_setopt($curlSession, CURLOPT_URL, $this->create_issue_url);
      curl_setopt($curlSession, CURLOPT_RETURNTRANSFER, TRUE);
      curl_setopt($curlSession, CURLOPT_POSTFIELDS, $jsonData);
      $response = curl_exec($curlSession);
      curl_close($curlSession);
      return $response;
    } catch (Exception $e) {
      throw new Exception($e->getMessage());
    }
  }

//Extracts the issue id from the server response so we can submit file attachment
  function getIssueId($serverReponse) {
    $responseArray = json_decode($serverReponse);
    try {
      foreach ($responseArray as $key => $val) {
        if ($key == 'id') {
          $issue = $val;
          return $issue;
        }
      }
    } catch (Exception $e) {
      throw new Exception($e->getMessage());
    }
  }

//Attaches files to issue
  function attachFiles($id, $form_state) {
    $url = $this->create_issue_url . $id . '/attachments/';

    $header = array(
      "Authorization: Basic " . $this->username,
      "X-Atlassian-Token: nocheck"
    );

    $fileNames = array();
    foreach ($_POST as $key => $value) {
      if (!isset($value['fid'])) {
        continue;
      }

      $fileData = array('size' => 0);
      if ($form_state['values'][$key]) {
        $file = file_load($form_state['values'][$key]);
      } else {
        $file = file_load($value['fid']);
      }
      if (is_object($file)) {
        $fileData = array(
          'tmp_name' => drupal_realpath($file->uri),
          'name' => $file->filename,
          'size' => intval($file->filesize),
          'mime' => $file->filemime,
        );
      }

      //Clear $response variable if set already
      if (isset($response)) {
        unset($response);
      }

      $file = array('file' => new CURLFile($fileData['tmp_name'], $fileData['mime'], $fileData['name']));
      if ($fileData['size'] > 0) {
        $curlSession = curl_init();
        curl_setopt($curlSession, CURLINFO_HEADER_OUT, TRUE);
        curl_setopt($curlSession, CURLOPT_HTTPHEADER, $header);
        curl_setopt($curlSession, CURLOPT_URL, $url);
        curl_setopt($curlSession, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($curlSession, CURLOPT_POSTFIELDS, $file);
        curl_setopt($curlSession, CURLOPT_SAFE_UPLOAD, TRUE);
        $response = curl_exec($curlSession);
        $decodedResponse = json_decode($response, TRUE);
        curl_close($curlSession);
        \Drupal::logger('Travel Services File Response')->notice($response);
        if (sizeof($decodedResponse) > 0) {
          $fileNames[] = $$fileData['name'];
        }
      }
    }//end foreach
    return $fileNames;
  }

//Parse response from JIRA and display message.
  function parseIssueResponse($issueResponse, $fileNames, $formTitle, $postData, $files) {
    $data = json_decode($issueResponse, TRUE);

    if (sizeof($fileNames) > 0) {
      $fileList = "</br>" . sizeof($fileNames) . " Files Uploaded Successfully";
    } else {
      $fileList = "<br>No Files Uploaded";
    }

    if ($data['key'] != '') {
      $ticket = $data['key'];
      //$id = $data['id'];
      //$url = $data['self'];
      $drupalMessage = 'Request successfully processed.<br/>' .
        'Ticket Number: ' . $ticket;
      $drupalMessage .= $fileList;
      drupal_set_message(t($drupalMessage));


      switch ($formTitle) {
        case 'Travel Voucher':
          $travel_form = $this->travel_voucher_form_fields();
          break;
        case 'Traveler ID':
          $travel_form = $this->traveler_id_form_fields();
          break;
        case 'Traveler Profile':
          $travel_form = $this->travel_profile_form_fields();
          break;
        case 'Travel Authorization':
          $travel_form = $this->travel_authorization_form_fields();
          break;
        case 'Traveler Amendment':
          $travel_form = $this->travel_amendment_form_fields();
          break;
        case 'Travel Cancellation':
          $travel_form = $this->travel_cancellation_form_fields();
          break;
        case 'Travel Question':
          $travel_form = $this->travel_question_form_fields();
          break;
        case 'Routing Change Update':
          $travel_form = $this->travel_routing_change_update_form_fields();
          break;
        case 'Concur Routing':
          $travel_form = $this->travel_concur_routing_form_fields();
          break;
      }

      $from = "noreply@epa.gov";
      $headers = "From: $from\r\n";
      $headers .= "Content-type: text/html; charset=utf-8\r\n";

      $decoded = json_decode($postData, TRUE);

      if ($decoded['fields']['customfield_10199']['value'] == 'No')
        unset($decoded['fields']['customfield_11424']);

      if ($decoded['fields']['customfield_10423']['value'] == 'No')
        unset($decoded['fields']['customfield_10424']);

      if ($decoded['fields']['customfield_10424']['value'] == 'No')
        unset($decoded['fields']['customfield_10425']);

      if ($decoded['fields']['customfield_10191']['value'] != 'Invitational (Non-EPA)') {
        unset($decoded['fields']['customfield_10140']);
        unset($decoded['fields']['customfield_10193']);
      }

      // "has ethics form been prepared" should only show in email if it's showing on the form. to get it to show on the form, select Yes for "another org paying?", then select "non-federal source" from the dropdown
      if ($decoded['fields']['customfield_10199']['value'] == 'Yes' && $decoded['fields']['customfield_11424']['value'] == 'Non-Federal Source') {
        ;
      } else {
        unset($decoded['fields']['customfield_11428']);
      }


      $message = "<br> <b>Travel Request Number:</b> " . $ticket;
      //The mail function inserts line-break every 900 characters or so, to fix add \r\n.
      //source: https://stackoverflow.com/questions/9999908/php-mail-function-randomly-adds-a-space-to-message-text
      foreach ($decoded['fields'] as $key => $val) {
        if ($key == 'project' || $key == 'issuetype' || $key == 'summary')
          continue;
        if (is_array($val)) {
          if (count($val) == 1) {
            if (isset($val[0]))
              $temp_val = $val[0]['value'];
            else
              $temp_val = $val['value'];
            if (!empty($temp_val))
              $message .= "\r\n<br> <b>" . $travel_form[$key] . ":</b> " . $temp_val;
          } else {
            $temp_msg = '';
            foreach ($val as $k => $v) {
              if (!empty($v['value']))
                $temp_msg .= $v['value'] . ", ";
            }
            $message .= "\r\n<br> <b>" . $travel_form[$key] . ":</b> " . trim($temp_msg, ',');
          }
        } else {
          if (!empty($val))
            $message .= "\r\n<br> <b>" . $travel_form[$key] . ":</b> " . $val;
        }
      }
      mail($decoded['fields']['customfield_10092'], $formTitle . " Form Submission", 'You have submitted the below ' . $formTitle . ' Form' . $message, $headers);
    } else {
      $drupalMessage = 'There was an error processing your request. Code-0003';
      drupal_set_message(t($drupalMessage), 'error');
      drupal_set_message(t(var_export($issueResponse, TRUE)), 'error');

    }
  }


  function travel_voucher_form_fields() {
    return array('proxy' => 'Completing for someone else?', 'customfield_10092' => 'Your @epa.gov Email', 'customfield_10090' => 'Your Name', 'customfield_10091' => 'Your Telephone',
      'customfield_10093' => 'Traveler L/C/O', 'customfield_10501' => 'Traveler division or equivalent', 'customfield_10095' => 'Departure Date',
      'customfield_10343' => 'Departure Time', 'customfield_10096' => 'Return Date', 'customfield_10342' => 'Return Time', 'customfield_10620' => 'Traveler @epa.gov Email',
      'customfield_10331' => 'Traveler name', 'customfield_10278' => 'Transportation Costs', 'customfield_10280' => 'Other Expenses',
      'customfield_10441' => 'First Taxi Date', 'customfield_10442' => 'First Taxi To', 'customfield_10443' => 'First Taxi From', 'customfield_10444' => 'Cost of First Taxi Trip',
      'customfield_10445' => 'Second Taxi Date', 'customfield_10446' => 'Second Taxi To', 'customfield_10447' => 'Second Taxi From', 'customfield_10448' => 'Cost of Second Taxi Trip',
      'customfield_10449' => 'Third Taxi Date', 'customfield_10450' => 'Third Taxi To', 'customfield_10451' => 'Third Taxi From', 'customfield_10453' => 'Cost of Third Taxi Trip',
      'customfield_10454' => 'Fourth Taxi Date', 'customfield_10455' => 'Fourth Taxi To', 'customfield_10456' => 'Fourth Taxi From', 'customfield_10457' => 'Cost of Fourth Taxi Trip',
      'customfield_10458' => 'Fifth Taxi Date', 'customfield_10480' => 'Fifth Taxi To', 'customfield_10460' => 'Fifth Taxi From', 'customfield_10461' => 'Cost of Fifth Taxi Trip',
      'customfield_10462' => 'Sixth Taxi Date', 'customfield_10463' => 'Sixth Taxi To', 'customfield_10464' => 'Sixth Taxi From', 'customfield_10465' => 'Cost of Sixth Taxi Trip',
      'customfield_10466' => 'Seventh Taxi Date', 'customfield_10481' => 'Seventh Taxi To', 'customfield_10482' => 'Seventh Taxi From', 'customfield_10469' => 'Cost of Seventh Taxi Trip',
      'customfield_10470' => 'Eighth Taxi Date', 'customfield_10471' => 'Eighth Taxi To', 'customfield_10474' => 'Eighth Taxi From', 'customfield_10475' => 'Cost of Eighth Taxi Trip',
      'customfield_10379' => 'Metro', 'customfield_10374' => 'Bus', 'customfield_10378' => 'Uber/Lyft', 'customfield_10381' => 'POV Mileage', 'customfield_10384' => 'Rental Car',
      'customfield_10385' => 'Rental Car Fuel', 'customfield_10383' => 'Rail', 'customfield_10387' => 'Tolls', 'customfield_10375' => 'Hotel Costs', 'customfield_10377' => 'Internet Fees',
      'customfield_10376' => 'Hotel Parking', 'customfield_10372' => 'Airport Parking', 'customfield_10373' => 'Cash Withdrawal Finance Fee', 'customfield_10371' => 'ATM Fees',
      'customfield_10380' => 'Other Expenses', 'customfield_10422' => 'Please Explain (Other Expenses)', 'showMealsSelect' => 'Were any meals provided through your registration costs or by the meeting sponsor?',
      'customfield_10148' => 'Breakfast', 'customfield_10149' => 'Lunch', 'customfield_10150' => 'Dinner', 'customfield_11420' => 'Reimburse to Government Credit Card', 'customfield_10151' => 'Meals Paid for All Days of Travel',
      'customfield_10141' => 'Please provide/explain any special requirements related to this travel request', 'customfield_10308' => 'Comments on Travel Experience', 'customfield_10191' => 'Travel Type',
    );
  }

  function traveler_id_form_fields() {
    return array('proxy' => 'Completing for someone else?', 'customfield_10092' => 'Your @epa.gov Email', 'customfield_10090' => 'Your Name', 'customfield_10091' => 'Your Telephone',
      'customfield_10620' => 'Traveler @epa.gov Email', 'customfield_10331' => 'Traveler name', 'customfield_10093' => 'Traveler L/C/O', 'customfield_10501' => 'Traveler division or equivalent',
    );
  }


  function travel_profile_form_fields() {
    return array('proxy' => 'Completing for someone else?', 'customfield_10277' => 'New Employee?', 'customfield_10092' => 'Your @epa.gov Email', 'customfield_10090' => 'Your Name', 'customfield_10091' => 'Your Telephone',
      'customfield_10620' => 'Traveler @epa.gov Email', 'customfield_10331' => 'Traveler name', 'customfield_10093' => 'Traveler L/C/O', 'customfield_10501' => 'Traveler division or equivalent',
      'customfield_10266' => 'Preferred Home Airport', 'customfield_10263' => 'Seating Preference', 'customfield_10354' => 'Frequent Flier Airlines and Account Numbers',
      'customfield_10120' => 'Smoking Preference', 'customfield_10262' => 'Bed Size Preference', 'customfield_10273' => 'Hotel Chain Preference(s)',
      'customfield_10274' => 'Frequent Member Account Number(s)', 'customfield_10291' => 'Additional Requests', 'customfield_11429' => 'Date of Birth', 'customfield_11430' => 'Emergency Contact Name and Phone Number',
    );
  }

  function travel_authorization_form_fields() {
    return array('proxy' => 'Completing for someone else?', 'customfield_10190' => 'Traveler type', 'customfield_10191' => 'Travel Type', 'customfield_10092' => 'Your @epa.gov Email', 'customfield_10090' => 'Your Name', 'customfield_10091' => 'Your Telephone',
      'customfield_10620' => 'Traveler @epa.gov Email', 'customfield_10331' => 'Traveler name', 'customfield_11423' => "Traveler's Phone Number",
      'customfield_10493' => 'Verify Traveler name as it appears on government issued ID used for airline travel', 'customfield_10093' => 'Traveler L/C/O', 'customfield_10501' => 'Traveler division or equivalent',
      'customfield_10199' => 'Another org paying?', 'customfield_11424' => 'Office', 'customfield_11425' => 'EPA office paying', 'customfield_10491' => 'Cross-funding label',
      'customfield_11426' => 'Agency Name', 'customfield_11427' => 'Name of Funding Organization', 'customfield_11428' => 'Has ethics form been prepared', 'customfield_10340' => 'Office POC',
      'customfield_10201' => 'Organization POC', 'customfield_10356' => 'Emergency contact name and phone', 'customfield_11422' => 'Daily Itinerary while Out of Country ',
      'customfield_10430' => 'List your Division Director and/or Branch Chief Name/s', 'customfield_10094' => 'Travel Description', 'customfield_10922' => 'Travel Purpose',
      'customfield_10195' => "Traveler's Qualification", 'customfield_10198' => 'Benefit to EPA', 'customfield_10357' => 'Destination Country Contact (Name/Phone/Organization)',
      'customfield_10426' => 'Is this Travel for a Conference', 'customfield_10921' => 'Conference code, if available', 'customfield_10246' => 'Will training dollars be used for this travel?',
      'customfield_10431' => 'Are others from your L/C/O attending this meeting too',
      'customfield_10431a' => 'Are others from your L/C/O attending this meeting too', 'customfield_10431b' => 'Are others from your L/C/O attending this meeting too',
      'customfield_10432a' => 'Please list other attendees names', 'customfield_10432b' => 'Please list other attendees names',
      'customfield_10432' => 'Please list other attendees names', 'customfield_10423' => 'Are there training dollars used for this travel',
      'customfield_10424' => 'Are you presenting', 'customfield_10140' => 'Is any of this travel reimbursable or in-kind', 'customfield_10193' => 'Has ethics form been approved?',
      'customfield_10492' => 'Passport Expiration Date', 'customfield_10435' => 'Have you made your own airline reservation?', 'customfield_10103' => 'Mode of Transportation',
      'customfield_10111' => 'A specific flight number or aircraft carrier or any additional transportation requirements', 'multiDestinations' => 'Multiple Destinations?',
      'customfield_10104' => 'Departing From("City, State, or Airport Code")', 'customfield_10095' => 'Date', 'customfield_10294' => 'Departure Time', 'customfield_10105c' => 'Destination Country',
      'customfield_10105' => 'Arriving At("City, State, or Airport Code")', 'customfield_10425' => 'What day/s are you presenting',
      'customfield_10247' => 'First Trip From', 'customfield_10344' => 'First Trip Arriving At("City, State, or Airport Code")', 'customfield_10316' => 'First Trip Departure Time', 'customfield_10253' => 'First Trip Departure Date',
      'customfield_10248' => 'Second Trip From', 'customfield_10345' => 'Second Trip Arriving At("City, State, or Airport Code")', 'customfield_10318' => 'Second Trip Departure Time', 'customfield_10255' => 'Second Trip Departure Date',
      'customfield_10249' => 'Third Trip From', 'customfield_10346' => 'Third Trip Arriving At("City, State, or Airport Code")', 'customfield_10320' => 'Third Trip Departure Time', 'customfield_10257' => 'Third Trip Departure Date',
      'customfield_10250' => 'Fourth Trip From', 'customfield_10347' => 'Fourth Trip Arriving At("City, State, or Airport Code")', 'customfield_10322' => 'Fourth Trip Departure Time', 'customfield_10259' => 'Fourth Trip Departure Date',
      'customfield_10251' => 'Fifth Trip From', 'customfield_10348' => 'Fifth Trip Arriving At("City, State, or Airport Code")', 'customfield_10324' => 'Fifth Trip Departure Time', 'customfield_10261' => 'Fifth Trip Departure Date',
      'customfield_10301' => 'Returning Date', 'customfield_10302' => 'Returning Time', 'customfield_10098' => 'Is Personal/Annual Leave Requested?', 'customfield_10099' => 'Dates of Approved Leave',
      'customfield_10112' => 'Have you already reserved a hotel room?', 'customfield_10281' => 'Hotel name(s) and phone number(s) for each destination', 'customfield_10370' => 'Hotel room rate(s)',
      'hotelPreference' => 'Do you have a hotel preference?', 'customfield_10434' => 'If no hotel preference, please provide the address of your meeting location',
      'customfield_10117' => 'Specify hotel name and location (street address)', 'blockOfRooms' => 'Block of rooms reserved?', 'customfield_10119' => 'Name of Block',
      'customfield_10411' => 'Mode of Transportation', 'customfield_11431' => 'Roundtrip POV, miles', 'customfield_10412' => 'Private Vehicle Parking at Airport, cost/day',
      'customfield_10413' => 'Taxi, approximate cost', 'customfield_10414' => 'Public transportation, cost', 'customfield_10415' => 'Other anticipated expenses',
      'customfield_10416' => 'Phone Calls', 'customfield_10418' => 'ATM Fees', 'customfield_10377' => 'Internet Fees', 'customfield_10417' => 'Supplies', 'customfield_10386' => 'Taxis',
      'customfield_10419' => 'Baggage Fee', 'customfield_10420' => 'Conference Registration Fee', 'customfield_10421' => 'Public Transit', 'customfield_10380' => 'Other, explain',
      'customfield_10141' => 'Special requirements related to travel request', 'customfield_11827' => 'Have you taken the HSTOS Training?',
      'customfield_11823' => 'Who is your Deputy Ethics Official?', 'customfield_10492date' => 'Passport expiration date?', 'customfield_11825' => 'Sponsoring org',
      'customfield_11822' => 'Position Title', 'customfield_11821' => 'Traveler\'s Grade', 'customfield_11826' => 'Are you traveling outside the country?',
    );
  }

  function travel_amendment_form_fields() {
    return array('proxy' => 'Completing for someone else?', 'customfield_10092' => 'Your @epa.gov Email', 'customfield_10090' => 'Your Name', 'customfield_10091' => 'Your Telephone',
      'customfield_10620' => 'Traveler @epa.gov Email', 'customfield_10331' => 'Traveler name', 'customfield_10093' => 'Traveler L/C/O', 'customfield_10501' => 'Traveler division or equivalent',
      'customfield_10208' => 'Travel Request Number', 'customfield_10303' => 'Destination', 'customfield_10095' => 'Departure Date', 'customfield_10209' => 'Requested Change', 'customfield_10191' => 'Travel Type',
    );
  }

  function travel_cancellation_form_fields() {
    return array('proxy' => 'Completing for someone else?', 'customfield_10092' => 'Your @epa.gov Email', 'customfield_10090' => 'Your Name', 'customfield_10091' => 'Your Telephone',
      'customfield_10620' => 'Traveler @epa.gov Email', 'customfield_10331' => 'Traveler name', 'customfield_10093' => 'Traveler L/C/O', 'customfield_10501' => 'Traveler division or equivalent',
      'customfield_10208' => 'Travel Request Number', 'customfield_10303' => 'Destination', 'customfield_10095' => 'Departure Date', 'customfield_10290' => 'Explanation', 'customfield_10191' => 'Travel Type',
    );
  }

  function travel_question_form_fields() {
    return array('proxy' => 'Completing for someone else?', 'customfield_10092' => 'Your @epa.gov Email', 'customfield_10090' => 'Your Name', 'customfield_10091' => 'Your Telephone',
      'customfield_10620' => 'Traveler @epa.gov Email', 'customfield_10331' => 'Traveler name', 'customfield_10093' => 'Traveler L/C/O', 'customfield_10501' => 'Traveler division or equivalent',
      'customfield_10390' => 'Please state your question/comment in the field below',
    );
  }

  function travel_routing_change_update_form_fields() {
    return array('proxy' => 'Completing for someone else?', 'customfield_10092' => 'Your @epa.gov Email', 'customfield_10090' => 'Your Name', 'customfield_10091' => 'Your Telephone',
      'customfield_10620' => 'Traveler @epa.gov Email', 'customfield_10331' => 'Traveler name', 'customfield_10093' => 'Traveler L/C/O', 'customfield_10501' => 'Traveler division or equivalent',
      'customfield_10390' => 'Please state your question/comment in the field below', 'customfield_10191' => 'Travel Type',
    );
  }

  function travel_concur_routing_form_fields() {
    return array('proxy' => 'Completing for someone else?', 'customfield_10092' => 'Your @epa.gov Email', 'customfield_10090' => 'Your Name', 'customfield_10091' => 'Your Telephone',
      'customfield_10620' => 'Traveler @epa.gov Email', 'customfield_10331' => 'Traveler name', 'customfield_10093' => 'Traveler L/C/O', 'customfield_10501' => 'Traveler division or equivalent',
      'customfield_10209' => 'Please state your requested Concur update in the box below',
    );
  }
}
