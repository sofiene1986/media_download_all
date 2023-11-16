<?php

namespace Drupal\media_download_all\Cache;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Cache invalidator to clean up MDA archives when caches are cleared.
 */
class MdaCacheTagsInvalidator implements CacheTagsInvalidatorInterface {

  /**
   * The cache backend.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * MdaCacheTagsInvalidator constructor.
   *
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache backend.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(CacheBackendInterface $cache, EntityTypeManagerInterface $entity_type_manager) {
    $this->cache = $cache;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function invalidateTags(array $tags) {
    $entity_types = array_keys($this->entityTypeManager->getDefinitions());
    foreach ($tags as $tag) {
      $tag_parts = explode(':', $tag);
      // Look for a cache tag in the form <entity_type>:<entity_id>.
      if (count($tag_parts) === 2 && is_numeric($tag_parts[1]) && in_array($tag_parts[0], $entity_types, TRUE)) {
        $cid = 'media_download_all:' . $tag_parts[0] . ':' . $tag_parts[1];
        $cache = $this->cache->get($cid);
        if ($cache) {
          $cached_files = $cache->data;
          if (!empty($cached_files)) {
            foreach ($cached_files as $file) {
              unlink($file);
            }
          }
          $this->cache->delete($cid);
        }
        break;
      }
    }
  }

}
