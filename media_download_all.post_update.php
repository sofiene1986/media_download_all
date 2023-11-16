<?php

/**
 * @file
 * Post update functions for the Media download all module.
 */

/**
 * Implements hook_post_update_NAME().
 */
function media_download_all_post_update_rename_class(&$sandbox) {
  // Rename class MDACacheTagsInvalidator to MdaCacheTagsInvalidator.
  // Rebuild the cache, see https://www.drupal.org/node/2960601.
}
