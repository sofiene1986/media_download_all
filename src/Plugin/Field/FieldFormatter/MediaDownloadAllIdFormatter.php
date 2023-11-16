<?php

namespace Drupal\media_download_all\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceIdFormatter;
use Drupal\media_download_all\Traits\MdaFormatterTrait;

/**
 * Plugin implementation of the 'media_download_all' formatter.
 *
 * @FieldFormatter(
 *   id = "media_download_all_entity_id",
 *   label = @Translation("Entity ID (MDA)"),
 *   field_types = {
 *     "entity_reference"
 *   }
 * )
 */
class MediaDownloadAllIdFormatter extends EntityReferenceIdFormatter {

  use MdaFormatterTrait;

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = parent::viewElements($items, $langcode);
    return $this->appendMdaLink($elements, $items, $langcode);

  }

}
