<?php


namespace Drupal\mymodule\Plugin\migrate\source;

use Drupal\migrate\Plugin\migrate\source\SqlBase;
use Drupal\migrate\Row;

/**
 * @MigrateSource(
 *   id = "all_windows_shares",
 *   source_module = "content_migrations",
 * )
 */
class AllWindowsShares extends SqlBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    // Source data is queried from 'AllWindowsShares' table.
    $query = $this->select('AllWindowsShares', 'aws')
      ->fields('aws', [
        'UserFolder',
        'SiteCode',
        'OwnerADAccount',
        'FSRMQuotaSizeGB'
      ]);
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    $fields = [
      'user_folder' => $this->t('user_folder'),
      'site_code' => $this->t('site_code'),
      'owner_ad_account' => $this->t('owner_ad_account'),
      'fsrm_quota_size_gb' => $this->t('fsrm_quota_size_gb'),
      'owner_email' => $this->t('owner_email')
    ];
    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return [
      'tid' => [
        'type' => 'integer',
        'alias' => 'tid',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    // This example shows how source properties can be added in
    // prepareRow(). The source dates are stored as 2017-12-17
    // and times as 16:00. Drupal 8 saves date and time fields
    // in ISO8601 format 2017-01-15T16:00:00 on UTC.
    // We concatenate source date and time and add the seconds.
    // The same result could also be achieved using the 'concat'
    // and 'format_date' process plugins in the migration
    // definition.
    $owner = $row->getSourceProperty('owner_ad_account');
    $quota_size = $row->getSourceProperty('fsrm_quota_size_gb');
    $owner_and_size = 'N/A';
    $owner_email = 'N/A';
    if (!isset($quota_size)) {
      $quota_size = 'N/A';
    }
    if (isset($owner) && $owner != 'N/A') {
      $rawExplodedArray = explode('=', $owner, 3); // splits into 'CN', 'Last,First,OU', rest of the string
      $rawExplodedString = $rawExplodedArray[1];
      $rawNameArray = explode(',', $rawExplodedString); // splits into 'First', 'Last', rest of string
      $owner = $rawNameArray[0] . ', ' . $rawNameArray[1];
      $owner_and_size = $owner . ' - ' . $quota_size;
      //  CN = Carson, David,OU = AdmDiv - ORD - CIN - O
    }
    $row->setSourceProperty('owner_and_size', $owner_and_size);
    $row->setSourceProperty('owner_email', $owner_email);
    return parent::prepareRow($row);
  }

}
