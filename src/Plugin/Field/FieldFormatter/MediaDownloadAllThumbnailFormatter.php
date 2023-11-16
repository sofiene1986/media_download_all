<?php

namespace Drupal\media_download_all\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\media\Plugin\Field\FieldFormatter\MediaThumbnailFormatter;
use Drupal\media_download_all\Traits\MdaFormatterTrait;

/**
 * Plugin implementation of the 'media_download_all_thumbnail' formatter.
 *
 * @FieldFormatter(
 *   id = "media_download_all_thumbnail",
 *   label = @Translation("Thumbnail (MDA)"),
 *   field_types = {
 *     "entity_reference"
 *   }
 * )
 */
class MediaDownloadAllThumbnailFormatter extends MediaThumbnailFormatter {

  use MdaFormatterTrait;

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = parent::viewElements($items, $langcode);
    return $this->appendMdaLink($elements, $items, $langcode);
  }

}
