<?php

namespace Drupal\media_inline_embed\Plugin\Filter;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\filter\FilterProcessResult;
use Drupal\media\MediaInterface;
use Drupal\media\Plugin\Filter\MediaEmbed;

/**
 * Extends Drupal's MediaEmbed class.
 *
 * @Filter(
 *   id = "media_inline_embed",
 *   title = @Translation("Embed media inline"),
 *   description = @Translation("Embeds media items using a custom tag, <code>&lt;drupal-inline-media&gt;</code>. If used in conjunction with the 'Align/Caption' filters, make sure this filter is configured to run after them."),
 *   type = Drupal\filter\Plugin\FilterInterface::TYPE_TRANSFORM_REVERSIBLE,
 *   settings = {
 *     "default_view_mode" = "default",
 *     "allowed_view_modes" = {},
 *     "allowed_media_types" = {},
 *   },
 *   weight = 100,
 * )
 */
class MediaInlineEmbed extends MediaEmbed {

  protected $media_inline_config;
  /**
   * @param array $configuration
   * @param $plugin_id
   * @param $plugin_definition
   * @param EntityRepositoryInterface $entity_repository
   * @param EntityTypeManagerInterface $entity_type_manager
   * @param EntityDisplayRepositoryInterface $entity_display_repository
   * @param EntityTypeBundleInfoInterface $bundle_info
   * @param RendererInterface $renderer
   * @param LoggerChannelFactoryInterface $logger_factory
   * Constructs parent MediaEmbed, with the addition of our custom ORD_Work media load URLs
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityRepositoryInterface $entity_repository, EntityTypeManagerInterface $entity_type_manager, EntityDisplayRepositoryInterface $entity_display_repository, EntityTypeBundleInfoInterface $bundle_info, RendererInterface $renderer, LoggerChannelFactoryInterface $logger_factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_repository, $entity_type_manager, $entity_display_repository, $bundle_info, $renderer, $logger_factory);
    $this->media_inline_config = \Drupal::config('media_inline_embed.form');
  }

  /**
   * {@inheritdoc}
   */
  public function process($text, $langcode) {
    $result = new FilterProcessResult($text);

    if (stristr($text, '<drupal-inline-media') === FALSE) {
      return $result;
    }

    $dom = Html::load($text);
    $xpath = new \DOMXPath($dom);

    foreach ($xpath->query('//*[@data-entity-type="media" and normalize-space(@data-entity-uuid)!=""]') as $node) {
      /** @var \DOMElement $node */

      // Nodes with this attribute are provided by linkit. Therefore, they should not be handled as inline-media, but as a linkit link.
      // Pass over these entities instead of processing them.
      if ($node->hasAttribute('data-entity-substitution')) {
        continue;
      }

      $uuid = $node->getAttribute('data-entity-uuid');
      $view_mode_id = $node->getAttribute('data-view-mode') ?: $this->settings['default_view_mode'];

      // Delete the consumed attributes.
      $node->removeAttribute('data-entity-type');
      $node->removeAttribute('data-entity-uuid');
      $node->removeAttribute('data-view-mode');

      $media = $this->entityRepository->loadEntityByUuid('media', $uuid);
      assert($media === NULL || $media instanceof MediaInterface);
      if (!$media) {
        $this->loggerFactory->get('media')->error('During rendering of embedded media: the media item with UUID "@uuid" does not exist.', ['@uuid' => $uuid]);
      }
      else {
        $media = $this->entityRepository->getTranslationFromContext($media, $langcode);
        $media = clone $media;
        $this->applyPerEmbedMediaOverrides($node, $media);
      }

      $view_mode = NULL;
      if ($view_mode_id !== EntityDisplayRepositoryInterface::DEFAULT_DISPLAY_MODE) {
        $view_mode = $this->entityRepository->loadEntityByConfigTarget('entity_view_mode', "media.$view_mode_id");
        if (!$view_mode) {
          $this->loggerFactory->get('media')->error('During rendering of embedded media: the view mode "@view-mode-id" does not exist.', ['@view-mode-id' => $view_mode_id]);
        }
      }

      $build = $media && ($view_mode || $view_mode_id === EntityDisplayRepositoryInterface::DEFAULT_DISPLAY_MODE)
        ? $this->renderMedia($media, $view_mode_id, $langcode)
        : $this->renderMissingMediaIndicator();

      $build
      $requestedMedia = $this->requestMediaFromORD($uuid);

      if (empty($build['#attributes']['class'])) {
        $build['#attributes']['class'] = [];
      }
      // Any attributes not consumed by the filter should be carried over to the
      // rendered embedded entity. For example, `data-align` and `data-caption`
      // should be carried over, so that even when embedded media goes missing,
      // at least the caption and visual structure won't get lost.
      foreach ($node->attributes as $attribute) {
        if ($attribute->nodeName == 'class') {
          // We don't want to overwrite the existing CSS class of the embedded
          // media (or if the media entity can't be loaded, the missing media
          // indicator). But, we need to merge in CSS classes added by other
          // filters, such as filter_align, in order for those filters to work
          // properly.
          $build['#attributes']['class'] = array_unique(array_merge($build['#attributes']['class'], explode(' ', $attribute->nodeValue)));
        }
        else {
          $build['#attributes'][$attribute->nodeName] = $attribute->nodeValue;
        }
      }

      $this->renderIntoDomNode($build, $node, $result);
    }

    $result->setProcessedText(Html::serialize($dom));

    return $result;
  }

  /**
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  private function requestMediaFromORD($uuid) {
    $url = 'http://localhost:8090/epa_wysiwyg/loadDocumentMedia/' . $uuid;
    $method = 'GET';
    $options = [
      'form_params' => [
      ]
    ];

    $client = \Drupal::httpClient();

    $response = $client->request($method, $url, $options);
    $code = $response->getStatusCode();
    if ($code == 200) {
      $body = $response->getBody()->getContents();
    }
    return $body;
  }

}
