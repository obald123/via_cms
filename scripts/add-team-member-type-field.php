<?php

/**
 * @file
 * Adds field_member_type to team_member, so the same content type can carry
 * both Team and Board members — a board member needs exactly the same
 * fields (name, role, location, photo, bio) as a team member, so this reuses
 * the bundle rather than adding a parallel one.
 *
 * Defaults to "Team" so every existing team_member node (all of them, before
 * this field existed) keeps showing under Team with no migration needed —
 * the frontend's TeamSection treats anything other than "board" as Team.
 *
 * team_member is one of the editorial bundles the seeder never prunes (see
 * seed-content.php's header), so board members are added directly in Drupal
 * by staff, the same way a news item or testimonial is — never from data.ts.
 *
 * Idempotent — re-running it skips the field if it already exists.
 *
 *   drush php:script scripts/add-team-member-type-field.php
 */

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

if (!FieldStorageConfig::loadByName('node', 'field_member_type')) {
  FieldStorageConfig::create([
    'field_name' => 'field_member_type',
    'entity_type' => 'node',
    'type' => 'list_string',
    'cardinality' => 1,
    'settings' => ['allowed_values' => ['team' => 'Team', 'board' => 'Board']],
  ])->save();
  echo "created storage  field_member_type\n";
}

if (!FieldConfig::loadByName('node', 'team_member', 'field_member_type')) {
  FieldConfig::create([
    'field_name' => 'field_member_type',
    'entity_type' => 'node',
    'bundle' => 'team_member',
    'label' => 'Section',
    'description' => 'Board members are shown in their own section, before Team, on the About page.',
    'required' => TRUE,
    'default_value' => [['value' => 'team']],
  ])->save();
  echo "created field    field_member_type on team_member\n";
}

$formDisplay = \Drupal::service('entity_display.repository')->getFormDisplay('node', 'team_member', 'default');
if (!$formDisplay->getComponent('field_member_type')) {
  $formDisplay->setComponent('field_member_type', ['type' => 'options_select', 'weight' => 0])->save();
  echo "added to form    field_member_type\n";
}

echo "\nDone. Export config with `drush config:export` to capture this in config/sync.\n";
