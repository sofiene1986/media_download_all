<?php

namespace Drupal\media_download_all\Controller;

use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\File\FileSystem;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Drupal\Core\Access\AccessResult;

/**
 * Class DownloadController.
 */
class DownloadController extends ControllerBase {

  /**
   * Entity Field Manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * File system.
   *
   * @var \Drupal\Core\File\FileSystem
   */
  protected $fileSystem;

  /**
   * The request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * Constructs a new DownloadController object.
   *
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\Core\File\FileSystem $file_system
   *   The file system.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack service.
   */
  public function __construct(EntityFieldManagerInterface $entity_field_manager, FileSystem $file_system, RequestStack $request_stack) {
    $this->entityFieldManager = $entity_field_manager;
    $this->fileSystem = $file_system;
    $this->request = $request_stack->getCurrentRequest();
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException
   * @throws \Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_field.manager'),
      $container->get('file_system'),
      $container->get('request_stack')
    );
  }

  /**
   * The main download method.
   *
   * @param string $entity_type
   *   The entity type.
   * @param int $entity_id
   *   The entity id.
   * @param string $field_name
   *   The field name.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   Return the file.
   *
   * @throws \InvalidArgumentException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Core\Archiver\ArchiverException
   */
  public function download($entity_type, $entity_id, $field_name) {
    // Cache ID to store files for this entity.
    $cid = 'media_download_all:' . $entity_type . ':' . $entity_id;

    // Check for caches files for this entity.
    $cache = $this->cache()->get($cid);
    if ($cache) {
      $cached_files = $cache->data;
    }

    // If the file already exists, no need to recreate it.
    if (isset($cached_files[$field_name]) && file_exists($cached_files[$field_name])) {
      return $this->streamZipFile($cached_files[$field_name]);
    }

    $files = $this->getFiles($entity_type, $entity_id, $field_name);

    $referer = $this->request->server->get('HTTP_REFERER');
    $redirect_uri = $referer ?? "$entity_type/$entity_id";

    if (count($files) === 0) {
      $this->messenger()->addError($this->t('No files found for this entity to be downloaded'));
      return new RedirectResponse($redirect_uri);
    }

    $zip_files_directory = "private://media_download_all";
    $file_path = $this->fileSystem->realpath($zip_files_directory) . "/$entity_type-$entity_id-$field_name.zip";
    if ($this->fileSystem->prepareDirectory($zip_files_directory, FileSystemInterface::CREATE_DIRECTORY)) {
      batch_set($this->getBatch($files, $file_path, $entity_type, $entity_id, $field_name)->toArray());
      return batch_process($referer);
    }
    $this->messenger()->addError($this->t('Zip file directory not found.'));
    return new RedirectResponse($redirect_uri);
  }

  /**
   * Gets the batch builder.
   *
   * @param array $files
   *   An array of files.
   * @param string $file_path
   *   The file path.
   * @param string $entity_type
   *   The entity type.
   * @param string $entity_id
   *   The entity ID.
   * @param string $field_name
   *   The field name.
   *
   * @return \Drupal\Core\Batch\BatchBuilder
   *   The batch builder.
   */
  private function getBatch(array $files, $file_path, $entity_type, $entity_id, $field_name) {
    $batch_builder = (new BatchBuilder())
      ->setTitle($this->t('Compressing file(s) into a zip file'))
      ->setFinishCallback('media_download_all_operation_finished')
      ->setInitMessage($this->t('Starting'))
      ->setProgressMessage($this->t('Compressed @current out of @total.'))
      ->setErrorMessage($this->t('Compressing files has encountered an error'));

    foreach ($files as $fid => $file_name) {
      $batch_builder->addOperation('media_download_all_operation', [
        $file_path,
        $entity_type,
        $entity_id,
        $field_name,
        $fid,
        $file_name,
      ]);
    }
    return $batch_builder;
  }

  /**
   * Get files associated with the entity .
   *
   * @param string $entity_type
   *   The entity type.
   * @param string $entity_id
   *   The entity ID.
   * @param string $field_name
   *   The field name.
   *
   * @return array
   *   The file IDs.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  protected function getFiles($entity_type, $entity_id, $field_name) {
    $entity_storage = $this->entityTypeManager()->getStorage($entity_type);
    $entity = $entity_storage->load($entity_id);
    $media_files = $entity->{$field_name};
    $media_ids = [];

    foreach ($media_files->getValue() as $item) {
      if (isset($item['target_id'])) {
        $media_ids[] = $item['target_id'];
      }
    }

    $media_storage = $this->entityTypeManager()->getStorage('media');
    $media_entities = $media_storage->loadMultiple($media_ids);

    $files = [];

    foreach ($media_entities as $media) {
      $bundle = $media->bundle();
      $file_field_names = $this->getFileFieldsOfBundle($bundle);
      foreach ($file_field_names as $file_field_name) {
        foreach ($media->{$file_field_name}->getValue() as $item) {
          if (isset($item['target_id'])) {
            $files[$item['target_id']] = $media->getName();
          }
        }
      }
    }

    return $files;
  }

  /**
   * Get file field names.
   *
   * @param string $bundle
   *   The bundle of media.
   *
   * @return array
   *   Filed names contains file.
   */
  protected function getFileFieldsOfBundle($bundle) {

    $field_definitions = $this->entityFieldManager->getFieldDefinitions('media', $bundle);

    $field_names_filtered = [];
    foreach ($field_definitions as $field_name => $field_definition) {
      if ($field_name !== "thumbnail" && $field_definition->getFieldStorageDefinition()->getSetting('target_type') === 'file') {
        $field_names_filtered[] = $field_name;
      }
    }

    return $field_names_filtered;
  }

  /**
   * Method to stream created zip file.
   *
   * @param string $file_path
   *   File physical path.
   *
   * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
   *   The binary file.
   */
  protected function streamZipFile($file_path) {
    $binary_file_response = new BinaryFileResponse($file_path);
    $binary_file_response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, basename($file_path));
    return $binary_file_response;
  }

  /**
   * Checks access for this controller.
   *
   * @param string $entity_type
   *   The entity type.
   * @param int $entity_id
   *   The entity id.
   * @param string $field_name
   *   The field name.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   Return the result of the access check   *
   */
  public function access($entity_type, $entity_id, $field_name) {
    // Require permission to view the entity AND "View media" permission.
    $entity_storage = $this->entityTypeManager()->getStorage($entity_type);
    $entity = $entity_storage->load($entity_id);

    if ($this->currentUser()->hasPermission('view media')) {
      return $entity->access('view', NULL, TRUE);
    }

    return AccessResult::forbidden();
  }

}
