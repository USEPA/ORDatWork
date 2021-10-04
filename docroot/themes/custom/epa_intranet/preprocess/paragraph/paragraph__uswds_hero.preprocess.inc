<?php

/**
 * Implements hook_preprocess_paragraph__uswds_hero().
 */
function epa_intranet_preprocess_paragraph__uswds_hero(&$variables) {
  $variables['attributes']['class'][] = 'usa-hero';

  // We also need to set the inline CSS for the image.
  // This pre-processing is necessary for multiple heroes i.e. a hero carousel
  if (isset($variables['paragraph']) && isset($variables['paragraph']->field_uswds_image)) {
    if (!$variables['paragraph']->field_uswds_image->isEmpty()) {
      // Make sure we have a unique ID, in case of multiple Heroes.
      $numeric_id = $variables['paragraph']->field_uswds_image->entity->id();
      $identifier = 'uswds-hero-' . $numeric_id;
      $variables['attributes']['class'][] = $identifier;
      // Inline the CSS background image.
      $img = $variables['paragraph']->field_uswds_image->entity->createFileUrl(FALSE);
      $css = ".$identifier.paragraph--type--uswds-hero.usa-hero { background-image:url($img); }";
      $variables['#attached']['html_head'][] = [
        [
          '#tag' => 'style',
          '#value' => $css,
        ],
        $identifier,
      ];
    }
  }
}
