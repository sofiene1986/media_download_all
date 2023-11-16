<?php

namespace Drupal\media_download_all\Traits;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * Trait MdaFormatterTrait.
 */
trait MdaFormatterTrait {

  /**
   * Appends MDA link.
   *
   * @param array $elements
   *   The renderable array to append.
   * @param \Drupal\Core\Field\FieldItemListInterface $items
   *   The field values to be rendered.
   * @param string $langcode
   *   The language that should be used to render the field.
   *
   * @return array
   *   A renderable array with MDA link appended or not if the field is empty.
   */
  public function appendMdaLink(array $elements, FieldItemListInterface $items, $langcode) {
    if (empty($elements)) {
      return $elements;
    }
    $field_name = $items->getName();
    $entity = $items->getEntity();
    $entity_type = $entity->getEntityTypeId();
    $entity_id = $entity->id();
    $url = Url::fromUserInput("/media_download_all/$entity_type/$entity_id/$field_name");
    $download_link = Link::fromTextAndUrl('Download All Files', $url)->toRenderable();
    $download_link['#attributes']['class'] = ['media-download-all'];
    $elements[]['download_link'] = $download_link;
    return $elements;
  }

}
