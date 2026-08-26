<?php

/**
 * @file
 * Creates the `page_hero` content type and its three fields (slug, eyebrow,
 * subtitle — title comes from the node's own title field).
 *
 * page_hero is a new bundle, not a field added to an existing one, so this
 * creates the node type itself alongside its fields — the same idempotent,
 * additive pattern as add-project-fields.php, extended one step further back.
 * Nothing else on the site is touched.
 *
 *   drush php:script scripts/add-page-hero-fields.php
 */

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\NodeType;

if (!NodeType::load('page_hero')) {
  NodeType::create([
    'type' => 'page_hero',
    'name' => 'Page Hero',
    'description' => 'An editable eyebrow/title/subtitle heading, keyed by slug and reused wherever that heading appears in the frontend.',
    'new_revision' => TRUE,
    'display_submitted' => FALSE,
  ])->save();
  echo "created content type page_hero\n";
}

$fields = [
  'field_slug'     => ['label' => 'Slug', 'type' => 'string'],
  'field_eyebrow'  => ['label' => 'Eyebrow', 'type' => 'string'],
  'field_subtitle' => ['label' => 'Subtitle', 'type' => 'string_long'],
  'field_weight'   => ['label' => 'Weight', 'type' => 'integer'],
];

$formDisplay = \Drupal::service('entity_display.repository')
  ->getFormDisplay('node', 'page_hero', 'default');

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

  if (!FieldConfig::loadByName('node', 'page_hero', $name)) {
    FieldConfig::create([
      'field_name' => $name,
      'entity_type' => 'node',
      'bundle' => 'page_hero',
      'label' => $spec['label'],
      'required' => FALSE,
    ])->save();
    echo "created field    $name on page_hero\n";
  }

  if (!$formDisplay->getComponent($name)) {
    $formDisplay->setComponent($name, [
      'type' => $spec['type'] === 'string_long' ? 'string_textarea' : 'string_textfield',
      'weight' => $weight++,
    ]);
    echo "added to form     $name\n";
  }
}

$formDisplay->save();
echo "\nDone. Export config with `drush config:export` to capture this in config/sync.\n";
