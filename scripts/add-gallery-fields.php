<?php

/**
 * @file
 * Creates the `gallery_item` content type and its fields.
 *
 * One bundle covers both photos and videos: field_media_type says which, and
 * field_media holds either a .jpg or an .mp4. field_poster is the still frame
 * shown for videos before playback, which is what lets the frontend avoid
 * fetching any video bytes until a visitor presses play.
 *
 * Additive and idempotent, like the other add-*-fields.php scripts — creates
 * only what is missing and touches nothing else on the site.
 *
 *   drush php:script scripts/add-gallery-fields.php
 */

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\NodeType;

if (!NodeType::load('gallery_item')) {
  NodeType::create([
    'type' => 'gallery_item',
    'name' => 'Gallery Item',
    'description' => 'A photo or video shown on the Gallery page. Videos additionally appear in the Stories page film section when marked featured.',
    'new_revision' => TRUE,
    'display_submitted' => FALSE,
  ])->save();
  echo "created content type gallery_item\n";
}

/*
 * field_media is a generic file field rather than an image field, because the
 * same field holds both photos and videos. field_poster stays an image field.
 */
$fields = [
  'field_slug'       => ['label' => 'Slug', 'type' => 'string', 'widget' => 'string_textfield'],
  'field_media_type' => ['label' => 'Media type (photo or video)', 'type' => 'string', 'widget' => 'string_textfield'],
  'field_media'      => ['label' => 'File', 'type' => 'file', 'widget' => 'file_generic'],
  'field_poster'     => ['label' => 'Poster image (videos)', 'type' => 'image', 'widget' => 'image_image'],
  'field_caption'    => ['label' => 'Caption', 'type' => 'string_long', 'widget' => 'string_textarea'],
  'field_featured'   => ['label' => 'Featured', 'type' => 'boolean', 'widget' => 'boolean_checkbox'],
  'field_weight'     => ['label' => 'Weight', 'type' => 'integer', 'widget' => 'number'],
];

$formDisplay = \Drupal::service('entity_display.repository')
  ->getFormDisplay('node', 'gallery_item', 'default');

$weight = 0;
foreach ($fields as $name => $spec) {
  if (!FieldStorageConfig::loadByName('node', $name)) {
    FieldStorageConfig::create([
      'field_name' => $name,
      'entity_type' => 'node',
      'type' => $spec['type'],
      'cardinality' => 1,
    ])->save();
    echo "created storage  $name\n";
  }

  if (!FieldConfig::loadByName('node', 'gallery_item', $name)) {
    $config = [
      'field_name' => $name,
      'entity_type' => 'node',
      'bundle' => 'gallery_item',
      'label' => $spec['label'],
      'required' => FALSE,
    ];
    // Videos are large; the default 2MB file-field cap would reject them.
    if ($spec['type'] === 'file') {
      $config['settings'] = [
        'file_extensions' => 'jpg jpeg png webp mp4 webm',
        'max_filesize' => '64 MB',
        'file_directory' => 'gallery',
      ];
    }
    if ($spec['type'] === 'image') {
      $config['settings'] = [
        'file_extensions' => 'jpg jpeg png webp',
        'max_filesize' => '8 MB',
        'file_directory' => 'gallery',
      ];
    }
    FieldConfig::create($config)->save();
    echo "created field    $name on gallery_item\n";
  }

  if (!$formDisplay->getComponent($name)) {
    $formDisplay->setComponent($name, ['type' => $spec['widget'], 'weight' => $weight++]);
    echo "added to form     $name\n";
  }
}

$formDisplay->save();
echo "\nDone. Export config with `drush config:export` to capture this in config/sync.\n";
