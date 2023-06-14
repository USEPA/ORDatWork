<?php

namespace Drupal\single_content_sync;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\TypedData\TranslatableInterface;
use Drupal\file\FileInterface;
use Drupal\layout_builder\Plugin\Block\InlineBlock;
use Drupal\user\Entity\User;
use Drupal\menu_link_content\Entity\MenuLinkContent;

class ContentImporter implements ContentImporterInterface {

  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity repository.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The file system.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The content sync helper.
   *
   * @var \Drupal\single_content_sync\ContentSyncHelperInterface
   */
  protected $contentSyncHelper;

  /**
   * The inline block usage service.
   *
   * @var \Drupal\layout_builder\InlineBlockUsageInterface
   */
  protected $blockUsage;

  /**
   * ContentExporter constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system.
   * @param \Drupal\single_content_sync\ContentSyncHelperInterface $content_sync_helper
   *   The content sync helper.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EntityRepositoryInterface $entity_repository, ModuleHandlerInterface $module_handler, FileSystemInterface $file_system, ContentSyncHelperInterface $content_sync_helper) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityRepository = $entity_repository;
    $this->moduleHandler = $module_handler;
    $this->fileSystem = $file_system;
    $this->contentSyncHelper = $content_sync_helper;
  }

  /**
   * Get inline block usage from the service.
   *
   * @return \Drupal\layout_builder\InlineBlockUsageInterface
   *   The layout builder service.
   */
  private function blockUsage() {
    if (!$this->blockUsage) {
      $this->blockUsage = \Drupal::getContainer()->get('inline_block.usage');
    }
    return $this->blockUsage;
  }

  /**
   * {@inheritdoc}
   */
  public function doImport(array $content): EntityInterface {
    $storage = $this->entityTypeManager->getStorage($content['entity_type']);
    $definition = $this->entityTypeManager->getDefinition($content['entity_type']);

    // Check if there is an existing entity with the identical uuid.
    $entity = $this->entityRepository->loadEntityByUuid($content['entity_type'], $content['uuid']);

    // Create base for menu link if it exists later, for later processing
    $menu_link = null;

    // If not, create a new instance of the entity.
    if (!$entity) {
      $values = [
        'uuid' => $content['uuid'],
      ];
      if ($bundle_key = $definition->getKey('bundle')) {
        $values[$bundle_key] = $content['bundle'];
      }

      $entity = $storage->create($values);
    }

    switch ($content['entity_type']) {
      case 'node':
        $revision_log_message_addendum = '';
        // If no account email is provided, or if the provided email doesn't map
        // to an account, then set the current user as the author, and update
        // the message to include the original author's email.
        if (
          !($account_set = isset($content['base_fields']['author']))
          || !($account = user_load_by_mail($content['base_fields']['author']))
        ) {
          $revision_log_message_addendum =
            "\n\n"
            . $this->t(
              'Original Author: @author',
              [
                '@author' => (
                $account_set
                  ? $content['base_fields']['author']
                  : $this->t('Unknown')
                )
              ]
            );
          $account = User::load(\Drupal::currentUser()->id());
        }
        $entity->setOwner($account);

        if ($entity instanceof RevisionLogInterface) {
          $entity->setNewRevision();
          $entity->setRevisionCreationTime(\Drupal::time()->getCurrentTime());

          if (isset($content['base_fields']['revision_uid'])) {
            $entity->setRevisionUserId($content['base_fields']['revision_uid']);
          }

          if (isset($content['base_fields']['revision_log_message'])) {
            $entity->setRevisionLogMessage(
              $content['base_fields']['revision_log_message']
              . $revision_log_message_addendum
            );
          }
        }
        if (isset($content['base_fields']['menu_link_content'])) {
          $menu_link = $content['base_fields']['menu_link_content'];
        }
        break;

      case 'taxonomy_term':
        if ($content['base_fields']['parent']) {
          $entity->set('parent', $this->doImport($content['base_fields']['parent']));
        }
        break;

      case 'block_content':
        if (isset($content['base_fields']['enforce_new_revision']) && $entity instanceof RevisionableInterface) {
          $entity->setNewRevision();
        }
        break;
    }

    // Import values from base fields.
    $this->importBaseValues($entity, $content['base_fields']);

    // Alter importing entity by using hook_content_import_entity_alter().
    // Support of importing a new entity type can be provided in the hook.
    $this->moduleHandler->alter('content_import_entity', $content, $entity);

    // Import values from custom fields.
    $this->importCustomValues($entity, $content['custom_fields']);
    $this->createOrUpdate($entity);

    if (isset($menu_link)) {
      $menu_link['link'] = array('uri' => 'entity:node/'.$entity->getFields()['nid']->value);
      $menu_link_object = MenuLinkContent::create($menu_link);
      $menu_link_object->save();
    }



    // Sync translations of the entity.
    if (isset($content['translations']) && $entity instanceof TranslatableInterface) {
      foreach ($content['translations'] as $langcode => $translation_content) {
        $translated_entity = !$entity->hasTranslation($langcode) ? $entity->addTranslation($langcode) : $entity->getTranslation($langcode);

        $this->importBaseValues($translated_entity, $translation_content['base_fields']);
        $this->importCustomValues($translated_entity, $translation_content['custom_fields']);

        $translated_entity->set('content_translation_source', $entity->language()->getId());
        $translated_entity->save();
      }
    }

    return $entity;
  }

  /**
   * Create a new entity or update existing one in the proper way.
   *
   * The entity that we are importing can be already created during the import
   * if that entity existed as a reference. We need to update this entity to
   * use the same id and enforce update instead of insert.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity object.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function createOrUpdate(EntityInterface &$entity) {
    $definition = $this->entityTypeManager->getDefinition($entity->getEntityTypeId());
    $existing_entity = $this->entityRepository->loadEntityByUuid($entity->getEntityTypeId(), $entity->uuid());

    if ($existing_entity) {
      $entity->{$definition->getKey('id')} = $existing_entity->id();
      $entity->enforceIsNew(FALSE);
    }

    $entity->save();
  }

  /**
   * {@inheritdoc}
   */
  public function importCustomValues(FieldableEntityInterface $entity, array $fields) {
    foreach ($fields as $field_name => $field_value) {
      $this->setFieldValue($entity, $field_name, $field_value);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function importBaseValues(FieldableEntityInterface $entity, array $fields) {
    $values = $this->mapBaseFieldsValues($entity->getEntityTypeId(), $fields);

    foreach ($values as $field_name => $value) {
      $entity->set($field_name, $value);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setFieldValue(FieldableEntityInterface $entity, $field_name, $field_value) {
    if (!$entity->hasField($field_name)) {
      return;
    }

    // Clear value.
    if (is_null($field_value)) {
      $entity->set($field_name, $field_value);
      return;
    }

    $field_definition = $entity->getFieldDefinition($field_name);

    switch ($field_definition->getType()) {
      case 'boolean':
      case 'address':
      case 'daterange':
      case 'datetime':
      case 'smartdate':
        if (is_array($field_value)){
          if (isset($field_value['dates'])){
            foreach ($field_value['dates'] as $index => $value) {
              if (isset($value['rrule'])) {
                $field_value['dates'][$index]['rrule'] = $this->increaseRrule($field_value['dates'][$index]['rrule']);
              }
            }
            $entity->set($field_name, $field_value['dates']);
          }
          if (isset($field_value['rrule'])){
            // Save rrule
            foreach ($field_value['rrule'] as $index => $value) {
              $field_value['rrule'][$index]['custom_fields']['rid'][0]['value'] = $this->increaseRrule($field_value['rrule'][$index]['custom_fields']['rid'][0]['value']);
              $this->doImport($field_value['rrule'][$index]);
            }
          }
          if (isset($field_value['overrides'])) {
            // Save overrides
            foreach ($field_value['overrides'] as $index => $value) {
              // TODO - INCREASE RRULE IS WRONG
              $field_value['overrides'][$index]['custom_fields']['rrule'][0]['value'] = $this->increaseRrule($field_value['overrides'][$index]['custom_fields']['rrule'][0]['value']);
              $this->doImport($field_value['overrides'][$index]);
            }
          }
        } else {
          // Save dates
          $entity->set($field_name, $field_value);
        }
        break;
      case 'email':
      case 'geolocation':
      case 'link':
      case 'telephone':
      case 'timestamp':
      case 'decimal':
      case 'float':
      case 'integer':
      case 'list_float':
      case 'list_integer':
      case 'list_string':
      case 'text':
      case 'string':
      case 'string_long':
      case 'yearonly':
        $entity->set($field_name, $field_value);
        break;

      case 'text_long':
      case 'text_with_summary':
        if (is_array($field_value)) {
          foreach ($field_value as $item) {
            $embed_entities = $item['embed_entities'] ?? [];

            foreach ($embed_entities as $embed_entity) {
              $this->doImport($embed_entity);
            }
          }
        }

        $entity->set($field_name, $field_value);
        break;

      case 'entity_reference':
      case 'entity_reference_revisions':
      case 'dynamic_entity_reference':
        $values = [];
        foreach ($field_value as $child_entity) {
          // Import config relation just by setting target id.
          if (isset($child_entity['type']) && $child_entity['type'] === 'config') {
            $values[] = [
              'target_id' => $child_entity['value'],
            ];
            continue;
          }

          // If the entity was fully exported we do the full import.
          if ($this->isFullEntity($child_entity)) {
            $values[] = $this->doImport($child_entity);
            continue;
          }

          $reference_entity = $this->entityRepository->loadEntityByUuid($child_entity['entity_type'], $child_entity['uuid']);

          // Create a stub entity without custom field values.
          if (!$reference_entity) {
            $reference_entity_values = [
              'uuid' => $child_entity['uuid'],
            ];
            $definition = $this->entityTypeManager->getDefinition($child_entity['entity_type']);
            if ($bundle_key = $definition->getKey('bundle')) {
              $reference_entity_values[$bundle_key] = $child_entity['bundle'];
            }
            $reference_entity = $this->entityTypeManager->getStorage($child_entity['entity_type'])->create($reference_entity_values);
            $this->importBaseValues($reference_entity, $child_entity['base_fields']);
            $reference_entity->save();
          }

          $values[] = $reference_entity;
        }

        $entity->set($field_name, $values);
        break;

      case 'webform':
        $webform_storage = $this->entityTypeManager->getStorage('webform');

        if (isset($field_value['target_id'])) {
          if ($webform = $webform_storage->load($field_value['target_id'])) {
            $entity->set($field_name, $webform);
          }
        }
        break;

      case 'svg_image_field':
      case 'file':
      case 'image':
        $file_storage = $this->entityTypeManager->getStorage('file');
        $values = [];

        foreach ($field_value as $file_item) {
          $files = $file_storage->loadByProperties([
            'uri' => $file_item['uri'],
          ]);

          /** @var \Drupal\file\FileInterface $file */
          if (count($files)) {
            $file = reset($files);
          }
          else {
            $file_path = NULL;

            // Check if we have a file on the server. This is a case when you do
            // import content with assets from a zip file.
            if (file_exists($file_item['uri'])) {
              $file_path = $file_item['uri'];
            }
            elseif (file($file_item['url']) !== FALSE) {
              $file_path = $file_item['url'];
            }

            if (!$file_path) {
              continue;
            }

            // Create a file entity with the given uri as the file was already
            // imported in the proper directory. If the file is external then
            // we don't need to store file locally.
            $file = $file_storage->create([
              'uid' => 1,
              'status' => FileInterface::STATUS_PERMANENT,
              'uri' => $file_item['uri'],
            ]);
            $file->save();
          }

          $file_value = [
            'target_id' => $file->id(),
          ];

          if (isset($file_item['alt'])) {
            $file_value['alt'] = $file_item['alt'];
          }

          if (isset($file_item['title'])) {
            $file_value['title'] = $file_item['title'];
          }

          if (isset($file_item['description'])) {
            $file_value['description'] = $file_item['description'];
          }

          $values[] = $file_value;
        }

        $entity->set($field_name, $values);
        break;

      case 'metatag':
        $entity->set($field_name, [['value' => serialize($field_value)]]);
        break;

      case 'layout_section':
        if (!$this->moduleHandler->moduleExists('layout_builder')) {
          throw new \Exception('The layout could not be imported due to the layout_builder module was disabled.');
        }

        $imported_blocks = [];
        $block_list = $field_value['blocks'] ?? [];

        // Prepare entity to have id in the database to be used for inline block
        // usages.
        if ($block_list) {
          $this->createOrUpdate($entity);
        }

        foreach ($block_list as $block) {
          /** @var \Drupal\block_content\BlockContentInterface $new_block */
          $new_block = $this->doImport($block);

          if (!$this->blockUsage()->getUsage($new_block->id())) {
            $this->blockUsage()->addUsage($new_block->id(), $entity);
          }

          $old_revision_id = $block['base_fields']['block_revision_id'];
          $imported_blocks[$old_revision_id] = $new_block->getRevisionId();
        }

        // Get unserialized version of each section.
        $base64_sections = base64_decode($field_value['sections'] ?? $field_value);
        /** @var \Drupal\layout_builder\Section[] $sections */
        $sections = array_map(function (string $section) {
          return unserialize($section);
        }, explode('|', $base64_sections));

        foreach ($sections as $section) {
          $section_components = $section->getComponents();
          foreach ($section_components as $component) {
            if ($component->getPlugin() instanceof InlineBlock) {
              $configuration = $component->toArray()['configuration'];
              if (isset($configuration['block_revision_id']) && isset($imported_blocks[$configuration['block_revision_id']])) {
                // Replace the old revision id with a new revision id.
                $configuration['block_revision_id'] = $imported_blocks[$configuration['block_revision_id']];
                $component->setConfiguration($configuration);
              }
            }
          }
        }

        $entity->set($field_name, $sections);
        break;
    }

    // Alter setting a field value during the import by using
    // hook_content_import_field_value(). Support of importing a new field type
    // can be provided in the hook.
    $this->moduleHandler->alter('content_import_field_value', $entity, $field_name, $field_value);
  }

  /**
   * {@inheritdoc}
   */
  public function importFromFile(string $file_real_path): EntityInterface {
    if (!file_exists($file_real_path)) {
      throw new \Exception('The requested file does not exist.');
    }

    $file_content = file_get_contents($file_real_path);

    if (!$file_content) {
      throw new \Exception('The requested file could not be downloaded.');
    }

    $content = $this->contentSyncHelper->validateYamlFileContent($file_content);

    return $this->doImport($content);
  }

  /**
   * {@inheritdoc}
   */
  public function importFromZip(string $file_real_path): void {
    // Extract zip files to the unique local directory.
    $zip = $this->contentSyncHelper->createZipInstance($file_real_path);
    $import_directory = $this->contentSyncHelper->createImportDirectory();
    $zip->extract($import_directory);

    $content_file_path = NULL;
    $batch = [
      'title' => $this->t('Importing entities'),
      'operations' => [],
      'file' => '\Drupal\single_content_sync\ContentBatchImporter',
      'finished' => '\Drupal\single_content_sync\ContentBatchImporter::batchImportFinishedCallback',
    ];

    // Always import assets first, even if they're at the end of ZIP archive.
    foreach ($zip->listContents() as $zip_file) {
      $original_file_path = "{$import_directory}/{$zip_file}";

      if (strpos($zip_file, 'assets') === 0) {
        $batch['operations'][] = [
          '\Drupal\single_content_sync\ContentBatchImporter::batchImportAssets',
          [$original_file_path, $zip_file],
        ];
      }
    }

    foreach ($zip->listContents() as $zip_file) {
      $original_file_path = "{$import_directory}/{$zip_file}";

      if (strpos($zip_file, 'assets') === FALSE) {
        $content_file_path = $original_file_path;

        $batch['operations'][] = [
          '\Drupal\single_content_sync\ContentBatchImporter::batchImportFile',
          [$original_file_path],
        ];
      }
    }

    $batch['operations'][] = [
      '\Drupal\single_content_sync\ContentBatchImporter::cleanImportDirectory',
      [$import_directory],
    ];

    if (is_null($content_file_path)) {
      throw new \Exception('The content file in YAML format could not be found.');
    }

    batch_set($batch);
  }

  /**
   * {@inheritdoc}
   */
  public function importAssets(string $extracted_file_path, string $zip_file_path): void {
    $default_scheme = $this->contentSyncHelper->getDefaultFileScheme();

    // Use default scheme instead of the assets destinations.
    $destination = str_replace('assets/', "{$default_scheme}://", $zip_file_path);
    $directory = $this->fileSystem->dirname($destination);
    $this->contentSyncHelper->prepareFilesDirectory($directory);
    $this->fileSystem->move($extracted_file_path, $destination, FileSystemInterface::EXISTS_REPLACE);
  }

  /**
   * {@inheritdoc}
   */
  public function mapBaseFieldsValues($entity_type_id, array $values) {
    $map_values = [];

    switch ($entity_type_id) {
      case 'node':
        $node = [
          'title' => $values['title'],
          'langcode' => $values['langcode'],
          'created' => $values['created'],
          'status' => $values['status'],
        ];
        // We check if node url alias is filled in.
        if (isset($values['url'])) {
          $node['path'] = [
            'alias' => $values['url'],
            'pathauto' => empty($values['url']),
          ];
        }
        return $node;

      case 'user':
        return [
          'mail' => $values['mail'],
          'init' => $values['init'],
          'name' => $values['name'],
          'created' => $values['created'],
          'status' => $values['status'],
          'timezone' => $values['timezone'],
        ];

      case 'block_content':
        return [
          'langcode' => $values['langcode'],
          'info' => $values['info'],
          'reusable' => $values['reusable'],
        ];

      case 'media':
        return [
          'langcode' => $values['langcode'],
          'name' => $values['name'],
          'status' => $values['status'],
          'created' => $values['created'],
        ];

      case 'taxonomy_term':
        return [
          'name' => $values['name'],
          'weight' => $values['weight'],
          'langcode' => $values['langcode'],
          'description' => $values['description'],
        ];

      case 'paragraph':
        return [
          'langcode' => $values['langcode'],
          'created' => $values['created'],
          'status' => $values['status'],
        ];
    }

    return $map_values;
  }

  /**
   * Validates whether an entity array is a full entity array or not.
   *
   * @param array $entity
   *   The entity array to be validated.
   *
   * @return bool
   *   If the entity is a full entity array will return TRUE,
   *   else will return FALSE.
   */
  protected function isFullEntity(array $entity): bool {
    return isset($entity['uuid']) && isset($entity['entity_type']) && isset($entity['base_fields']) && isset($entity['custom_fields']);
  }

  /**
   * Updates RRULE IDs to avoid potential collisions
   * @param $rrule
   * @return float|int|void
   */
  protected function increaseRrule($rrule) {
    if (isset($rrule) && is_numeric($rrule)) {
      return $rrule * 1000;
    }
  }

}
