<?php

namespace Drupal\media_download_all\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceEntityFormatter;
use Drupal\media_download_all\Traits\MdaFormatterTrait;

/**
 * Plugin implementation of the 'media_download_all_entity_view' formatter.
 *
 * @FieldFormatter(
 *   id = "media_download_all_entity_view",
 *   label = @Translation("Rendered entity (MDA)"),
 *   field_types = {
 *     "entity_reference"
 *   }
 * )
 */
class MediaDownloadAllEntityFormatter extends EntityReferenceEntityFormatter {

  use MdaFormatterTrait;

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {

    $elements = parent::viewElements($items, $langcode);
    return $this->appendMdaLink($elements, $items, $langcode);

  }

}
