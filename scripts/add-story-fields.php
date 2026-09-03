<?php

/**
 * @file
 * Adds field_youtube_id, field_published_at and field_duration to the story
 * content type.
 *
 * Stories in the "Videos" category are films from VIA's Restore Local channel
 * rather than written pieces: they carry a YouTube id instead of body copy, and
 * the frontend renders them as a click-to-play card. A written story simply
 * leaves this empty.
 *
 * field_published_at is the ISO date (YYYY-MM-DD) used to sort every story
 * listing newest-first. It exists separately from the human-readable "date"
 * field because a video's date field used to double as its duration string
 * ("5:26"), which made chronological sorting impossible. field_duration holds
 * that duration text on its own, shown as a badge instead.
 *
 * Additive and idempotent, like the other add-*-fields scripts — see
 * scripts/add-project-fields.php for why this route is used instead of
 * `drush config:import` on this server.
 *
 *   drush php:script scripts/add-story-fields.php
 */

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

$fields = [
  'field_youtube_id' => [
    'type' => 'string',
    'label' => 'YouTube video ID',
    'description' => 'The 11-character id from a youtube.com/watch?v=… URL. Leave empty for written stories.',
    'widget' => 'string_textfield',
    'weight' => 40,
  ],
  'field_published_at' => [
    'type' => 'datetime',
    'settings' => ['datetime_type' => 'date'],
    'label' => 'Published date',
    'description' => 'ISO date used to sort stories newest-first. Required for every story, written or video.',
    'widget' => 'datetime_default',
    'weight' => 41,
  ],
  'field_duration' => [
    'type' => 'string',
    'label' => 'Video duration',
    'description' => 'Display duration, e.g. "5:26". Set only for videos.',
    'widget' => 'string_textfield',
    'weight' => 42,
  ],
];

$formDisplay = \Drupal::service('entity_display.repository')
  ->getFormDisplay('node', 'story', 'default');
$formDisplayChanged = FALSE;

foreach ($fields as $name => $spec) {
  if (!FieldStorageConfig::loadByName('node', $name)) {
    FieldStorageConfig::create([
      'field_name' => $name,
      'entity_type' => 'node',
      'type' => $spec['type'],
      'cardinality' => 1,
      'settings' => $spec['settings'] ?? [],
    ])->save();
    echo "created storage  $name\n";
  }

  if (!FieldConfig::loadByName('node', 'story', $name)) {
    FieldConfig::create([
      'field_name' => $name,
      'entity_type' => 'node',
      'bundle' => 'story',
      'label' => $spec['label'],
      'description' => $spec['description'],
      'required' => FALSE,
    ])->save();
    echo "created field    $name on story\n";
  }

  if (!$formDisplay->getComponent($name)) {
    $formDisplay->setComponent($name, ['type' => $spec['widget'], 'weight' => $spec['weight']]);
    $formDisplayChanged = TRUE;
    echo "added to form     $name\n";
  }
}

if ($formDisplayChanged) {
  $formDisplay->save();
}

echo "\nDone. Export config with `drush config:export` to capture this in config/sync.\n";
