<?php

/**
 * @file
 * Adds an optional email field to team_member nodes for the public team page.
 *
 * Idempotent: existing field storage, field instances, and form components are
 * preserved. After changing this file, deploy with scripts/deploy.sh.
 */

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

if (!FieldStorageConfig::loadByName('node', 'field_email')) {
  FieldStorageConfig::create([
    'uuid' => '4e5ee0be-a6f2-4da9-8015-0fc2ab1d37a5',
    'field_name' => 'field_email',
    'entity_type' => 'node',
    'type' => 'email',
    'cardinality' => 1,
  ])->save();
  echo "created storage  field_email\n";
}

if (!FieldConfig::loadByName('node', 'team_member', 'field_email')) {
  FieldConfig::create([
    'uuid' => 'bc5555e1-b9c2-4638-b4c0-834ed2f73504',
    'field_name' => 'field_email',
    'entity_type' => 'node',
    'bundle' => 'team_member',
    'label' => 'Email',
    'description' => 'Optional email address. When set, visitors can contact this team member from the website.',
    'required' => FALSE,
  ])->save();
  echo "created field    field_email on team_member\n";
}

$formDisplay = \Drupal::service('entity_display.repository')
  ->getFormDisplay('node', 'team_member', 'default');
if (!$formDisplay->getComponent('field_email')) {
  $formDisplay->setComponent('field_email', [
    'type' => 'email_default',
    'weight' => 7,
  ])->save();
  echo "added to form    field_email\n";
}

echo "\nDone. The optional Email field is available when editing team members.\n";
