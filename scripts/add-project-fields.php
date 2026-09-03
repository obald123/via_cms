<?php

/**
 * @file
 * Adds the fields the project content type needs for the TerraFund data.
 *
 * The project records now come from the TerraFund "Meet the Champions" base,
 * which describes each champion organisation rather than a funded work package.
 * That data has a cohort, an organisation type, a jobs figure, a website and a
 * short profile excerpt, and it has no funding amount, funder, status,
 * community count or progress percentage.
 *
 * This is deliberately additive: the superseded fields (field_funding,
 * field_funder, field_status, field_communities, field_progress, field_result,
 * field_image, field_category, field_body) are left in place with their content
 * intact. The API controller and the seeder simply stop reading them, so nothing
 * is lost if the old shape is ever needed again.
 *
 * Idempotent — re-running it skips fields that already exist.
 *
 *   drush php:script scripts/add-project-fields.php
 */

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

$fields = [
  'field_cohort'   => ['label' => 'Cohort', 'type' => 'string'],
  'field_org_type' => ['label' => 'Organisation type', 'type' => 'string'],
  'field_jobs'     => ['label' => 'Jobs to be created', 'type' => 'string'],
  'field_website'  => ['label' => 'Website', 'type' => 'string'],
  'field_excerpt'  => ['label' => 'Excerpt', 'type' => 'string_long'],
  // Delivered to date, against the targets above. Left empty until real
  // reported figures exist for a project — the frontend shows progress only
  // where a value is present, never a zeroed-out bar.
  'field_trees_done'    => ['label' => 'Trees grown to date', 'type' => 'string'],
  'field_hectares_done' => ['label' => 'Hectares restored to date', 'type' => 'string'],
  'field_jobs_done'     => ['label' => 'Jobs created to date', 'type' => 'string'],
];

$formDisplay = \Drupal::service('entity_display.repository')
  ->getFormDisplay('node', 'project', 'default');

$weight = 40;
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

  if (!FieldConfig::loadByName('node', 'project', $name)) {
    FieldConfig::create([
      'field_name' => $name,
      'entity_type' => 'node',
      'bundle' => 'project',
      'label' => $spec['label'],
      'required' => FALSE,
    ])->save();
    echo "created field    $name on project\n";
  }

  // Without a form-display entry the field exists but editors never see it.
  if (!$formDisplay->getComponent($name)) {
    $formDisplay->setComponent($name, [
      'type' => $spec['type'] === 'string_long' ? 'string_textarea' : 'string_textfield',
      'weight' => $weight++,
    ]);
    echo "added to form     $name\n";
  }
}

$formDisplay->save();
echo "\nDone. Export config with `drush config:export` to capture these in config/sync.\n";
