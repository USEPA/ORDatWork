<?php

/**
 * Implements hook_preprocess_paragraph__uswds_hero().
 */
function epa_intranet_preprocess_paragraph__uswds_hero(&$variables) {
  $variables['attributes']['class'][] = 'usa-hero';
  // it looks like this is deprecated now. In drupal 9, it appears to assign the image styling approprately without custom code required.
  // Leaving this function in case heroes require any tuning in the future
}
