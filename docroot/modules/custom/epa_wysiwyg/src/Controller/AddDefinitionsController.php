<?php

namespace Drupal\epa_wysiwyg\Controller;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Routing\AccessAwareRouterInterface;
use Drupal\Core\Url;
use Drupal\epa_wysiwyg\Plugin\CKEditorPlugin\EPAAddDefinitions;
use Drupal\rest\ResourceResponse;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class AddDefinitionsController.
 */
class AddDefinitionsController extends ControllerBase {

  /**
   * Symfony\Component\HttpFoundation\Request definition.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * GuzzleHttp\Client definition.
   *
   * @var \GuzzleHttp\Client
   */
  protected $httpClient;

  /**
   * Psr\Log\LoggerInterface definition.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructor for our class.
   */
  public function __construct(Request $request, Client $http_client, LoggerInterface $logger) {
    $this->request = $request;
    $this->httpClient = $http_client;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack')->getCurrentRequest(),
      $container->get('http_client'),
      $container->get('logger.factory')->get('epa_wysiwyg')
    );
  }

  /**
   * Prints available terms.
   */
  public function getTerms() {
    $response = new Response();
    $post = $this->request->get('text');
    // Check that required parameter is present.
    if (empty($post)) {
      return $response;
    }

    $url = Url::fromUri(EPAAddDefinitions::SERVICE_ENDPOINT, ['query' => ['callback' => 'CKEditorAddDefinitions.dictionaryCallback']]);
    $post_data['form_params'] = ['text' => $post];

    try {
      $request = $this->httpClient->post($url->toString(), $post_data);
      $response->setContent($request->getBody());
    }
    catch (RequestException $e) {
      $this->logger->error('Error returned for add definitions term lookup: @error', ['@error' => $e->getMessage()]);
    }

    return $response;
  }


  public function getDocumentMediaItem($uuid) {
    $response = new Response();

    $entityRepository = \Drupal::service('entity.repository');

    $media = $entityRepository->loadEntityByUuid('media', $uuid);
    $media_uri = $media->field_document->entity->getFileUri();
    $image_url = \Drupal::service('file_url_generator')->generateAbsoluteString($media_uri);

    //establish as default view mode
    $view_mode = EntityDisplayRepositoryInterface::DEFAULT_DISPLAY_MODE;
    $build = \Drupal::entityTypeManager()->getViewBuilder('media')->view($media, 'link');
    $build['#theme'] = 'media';
    $build['#type'] = 'media';
    $build['#embed'] = TRUE;
    $rawField = $build['#media']->get('field_document')->view('link');

    $renderedObject = \Drupal::service('renderer')
      ->renderRoot($rawField);

    $renderedObject = preg_replace('/<!--(.*)-->/Uis', '', $renderedObject);

    $response->setContent($renderedObject);


    return $response;

  }

  /**
   * Equivalent to uuid loading in function, but accepts/looks up media item by its MID instead of UUID
   * @param $mid
   * @return Response
   */
  public function getDocumentMediaItemByMid($mid) {
    $response = new Response();

    $media = \Drupal\media\Entity\Media::load($mid);

    $build = \Drupal::entityTypeManager()->getViewBuilder('media')->view($media, 'link');
    $rawField = $build['#media']->get('field_document')->view('link');

    $renderedObject = \Drupal::service('renderer')
      ->renderRoot($rawField);

    $renderedObject = preg_replace('/<!--(.*)-->/Uis', '', $renderedObject);

    $response->setContent($renderedObject);


    return $response;

  }

}
