<?php

/**
 * @file
 * Adds the one field the Airtable project sync needs: a stable key linking a
 * `project` node to the Airtable row it was created from or matched to.
 *
 * Hidden from the staff edit form — it's sync-owned, not something a person
 * fills in. See AirtableSync::findUnlinkedByTitle() for how the ~115 project
 * profiles seeded before this integration existed get linked to their
 * Airtable row on the first sync, without creating duplicates.
 *
 * Idempotent — re-running it skips the field if it already exists.
 *
 *   drush php:script scripts/add-airtable-sync-fields.php
 */

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

if (!FieldStorageConfig::loadByName('node', 'field_airtable_uuid')) {
  FieldStorageConfig::create([
    'field_name' => 'field_airtable_uuid',
    'entity_type' => 'node',
    'type' => 'string',
    'cardinality' => 1,
  ])->save();
  echo "created storage  field_airtable_uuid\n";
}

if (!FieldConfig::loadByName('node', 'project', 'field_airtable_uuid')) {
  FieldConfig::create([
    'field_name' => 'field_airtable_uuid',
    'entity_type' => 'node',
    'bundle' => 'project',
    'label' => 'Airtable record ID (sync-managed, do not edit)',
    'required' => FALSE,
  ])->save();
  echo "created field    field_airtable_uuid on project\n";
}

echo "\nDone. Export config with `drush config:export` to capture this in config/sync.\n";
