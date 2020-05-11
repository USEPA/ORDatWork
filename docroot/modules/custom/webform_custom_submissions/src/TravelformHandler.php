<?php

namespace Drupal\webform_custom_submissions;

use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\webformSubmissionInterface;
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
  protected $field_helper;

  public function __construct($config) {
    $travel_services_config = $config->get('webform_custom_submissions.form');
    $raw_username = $travel_services_config->get('USERNAME');
    $issue_creation_url = $travel_services_config->get('CREATE_ISSUE_URL');

    $this->create_issue_url = $travel_services_config->get('CREATE_ISSUE_URL');
    $this->domestic_project = $travel_services_config->get('DOMESTIC_PROJECT');
    $this->international_project = $travel_services_config->get('INTERNATIONAL_PROJECT');
    $this->submission_client = new Client(['base_uri' => $issue_creation_url]);
    $this->username = explode(':', $raw_username);
    $this->vouchers_project = $travel_services_config->get('VOUCHERS_PROJECT');
    $this->field_helper = new FieldHelper();
  }

  /*
   * Script for getting, formatting and submitting travel services form data to JIRA
   */

  public function submitToJira(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission) {
    try {
      $form_data = $webform_submission->getData();
      $postData = $this->compilePOSTData($form_data);
      $postResponse = $this->sendPOSTData($postData);
      $decoded_response = json_decode($postResponse, TRUE);
      if (isset($decoded_response['id'])) {
        $page = explode('/', \Drupal::service('path.current')->getPath());
        \Drupal::logger('Travel Services Response ' . $page[sizeof($page) - 1])->notice($postResponse);
        $issueId = $this->getIssueId($postResponse);

        $filesUploaded = $this->attachFiles($issueId, $form_state, $form_data);
        $this->parseIssueResponse($postResponse, $filesUploaded, $form_data['formTitle'], $postData, $_FILES);
      } elseif (isset($decoded_response['errorMessages'])) {
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
    }
  }

  //Compiles POST data and returns the data formatted as JSON
  protected function compilePOSTData($form_data) {

    $dropDowns = $this->field_helper->get_dropdown_fields();

    $data = array('fields' => array());

    //Add POST variables to the array
    foreach ($form_data as $key => $val) {
      // ignore file uploads, we are handling these later
      if (isset($val['fid'])) {
        continue;
      }

      if ($key == 'customfield_10191') {
        $data['fields'][$key] = array('value' => $val);
        switch ($val) {
          case 'Invitational (Non-EPA)':
          case 'Domestic':
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
      elseif ($this->field_helper->is_checkbox_field($key)) {
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
    if ($form_data['proxy'] == 'Yes')
      $data['fields']['summary'] = $form_data['formTitle'] . ': ' . $data['fields']['customfield_10331'];
    else if ($form_data['proxy'] == 'No')
      $data['fields']['summary'] = $form_data['formTitle'] . ': ' . $data['fields']['customfield_10090'];

    if ($form_data['customfield_10431a'] == 'Yes' || $form_data['customfield_10431b'] == 'Yes')
      $data['fields']['customfield_10431'] = array('value' => 'Yes');

    $jsonData = json_encode($data);

    return $jsonData;
  }

  /**
   * Builds and sends cURL request for the form POST data
   * @param $jsonData
   * @return Exception|\Psr\Http\Message\ResponseInterface
   */
  protected function sendPOSTData($jsonData) {
    try {
      $header = array(
        "Authorization: Basic " . $this->username,
        "Content-Type: application/json"
      );
      //$curlSession = curl_init();
      //curl_setopt($curlSession, CURLOPT_HTTPHEADER, $header);
      //curl_setopt($curlSession, CURLOPT_URL, $this->create_issue_url);
      //curl_setopt($curlSession, CURLOPT_RETURNTRANSFER, TRUE);
      //curl_setopt($curlSession, CURLOPT_POSTFIELDS, $jsonData);
      //$response = curl_exec($curlSession);
      //curl_close($curlSession);
      $response = $this->submission_client->request('POST',
        $this->create_issue_url,
        ['json' => $jsonData, 'Content-Type' => "application/json",
          'auth' => ["{$this->username[0]}", "{$this->username[1]}"]]);

      return $response;
    } catch (Exception $e) {
      return new Exception($e->getMessage());
    }
  }

  //Extracts the issue id from the server response so we can submit file attachment
  protected function getIssueId($serverReponse) {
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
  protected function attachFiles($id, $form_state, $form_data) {
    $url = $this->create_issue_url . $id . '/attachments/';

    $header = array(
      'auth' => ["{$this->username[0]}", "{$this->username[1]}"],
      'X-Atlassian-Token' => "nocheck"
    );

    $fileNames = array();
    foreach ($form_data as $key => $value) {
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

      //$file = array('file' => new CURLFile($fileData['tmp_name'], $fileData['mime'], $fileData['name']));
      if ($fileData['size'] > 0) {
        //$curlSession = curl_init();
        //curl_setopt($curlSession, CURLINFO_HEADER_OUT, TRUE);
        //curl_setopt($curlSession, CURLOPT_HTTPHEADER, $header);
        //curl_setopt($curlSession, CURLOPT_URL, $url);
        //curl_setopt($curlSession, CURLOPT_RETURNTRANSFER, TRUE);
        //curl_setopt($curlSession, CURLOPT_POSTFIELDS, $file);
        //curl_setopt($curlSession, CURLOPT_SAFE_UPLOAD, TRUE);
        //$response = curl_exec($curlSession);
        //$decodedResponse = json_decode($response, TRUE);
        //curl_close($curlSession);

        $response = $this->submission_client->request('POST',
          $url,
          ['headers' => $header,
            'multipart' => [
              'name' => $fileData['tmp_name'],
              'contents' => $fileData['mime'],
              'filename' => $fileData['name']
            ]]);
        $decodedResponse = json_decode($response, TRUE);
        \Drupal::logger('Travel Services File Response')->notice($response);
        if (sizeof($decodedResponse) > 0) {
          $fileNames[] = $$fileData['name'];
        }
      }
    }//end foreach
    return $fileNames;
  }

//Parse response from JIRA and display message.
  protected function parseIssueResponse($issueResponse, $fileNames, $formTitle, $postData, $files) {
    $data = json_decode($issueResponse, TRUE);

    if (sizeof($fileNames) > 0) {
      $fileList = "</br>" . sizeof($fileNames) . " Files Uploaded Successfully";
    } else {
      $fileList = "<br>No Files Uploaded";
    }

    if ($data['key'] != '') {
      $ticket = $data['key'];
      $drupalMessage = 'Request successfully processed.<br/>' .
        'Ticket Number: ' . $ticket;
      $drupalMessage .= $fileList;
      drupal_set_message(t($drupalMessage));

      switch ($formTitle) {
        case 'Travel Voucher':
          $travel_form = $this->field_helper->travel_voucher_form_fields();
          break;
        case 'Traveler ID':
          $travel_form = $this->field_helper->traveler_id_form_fields();
          break;
        case 'Traveler Profile':
          $travel_form = $this->field_helper->travel_profile_form_fields();
          break;
        case 'Travel Authorization':
          $travel_form = $this->field_helper->travel_authorization_form_fields();
          break;
        case 'Traveler Amendment':
          $travel_form = $this->field_helper->travel_amendment_form_fields();
          break;
        case 'Travel Cancellation':
          $travel_form = $this->field_helper->travel_cancellation_form_fields();
          break;
        case 'Travel Question':
          $travel_form = $this->field_helper->travel_question_form_fields();
          break;
        case 'Routing Change Update':
          $travel_form = $this->field_helper->travel_routing_change_update_form_fields();
          break;
        case 'Concur Routing':
          $travel_form = $this->field_helper->travel_concur_routing_form_fields();
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
}
