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
  // Cohort and the free-text organisation type are deliberately kept off the
  // form — see the form section below — so they aren't put back here.
  if (!$formDisplay->getComponent($name) && !in_array($name, ['field_cohort', 'field_org_type'], TRUE)) {
    $formDisplay->setComponent($name, [
      'type' => $spec['type'] === 'string_long' ? 'string_textarea' : 'string_textfield',
      'weight' => $weight++,
    ]);
    echo "added to form     $name\n";
  }
}

/*
 * ── The project edit form staff actually use ─────────────────────────────
 *
 * Adding a project by hand in Drupal should need nothing but the form: the
 * organisation's name, whether it is a company or a non-profit, where it works,
 * a photo, its website, its description and its three outcome targets. So:
 *
 *  - Organisation type becomes a dropdown (field_organisation_type). The old
 *    free-text field_org_type let "Non profit" and "Non-profit" become two
 *    separate filter options on /projects. Its values are copied across once
 *    and it is then only hidden, never deleted — the API still falls back to it.
 *  - Everything the site shows gets a plain-language label, help text and a
 *    sensible order.
 *  - The superseded fields listed in the header above are hidden from the form.
 *    Hidden, not deleted: their stored content is untouched.
 */
if (!FieldStorageConfig::loadByName('node', 'field_organisation_type')) {
  FieldStorageConfig::create([
    'field_name' => 'field_organisation_type',
    'entity_type' => 'node',
    'type' => 'list_string',
    'cardinality' => 1,
    // Keys match the strings the frontend has always filtered on.
    'settings' => ['allowed_values' => [
      'Non-profit' => 'Non-profit organisation',
      'For-profit' => 'Company (for-profit)',
    ]],
  ])->save();
  echo "created storage  field_organisation_type\n";
}
if (!FieldConfig::loadByName('node', 'project', 'field_organisation_type')) {
  FieldConfig::create([
    'field_name' => 'field_organisation_type',
    'entity_type' => 'node',
    'bundle' => 'project',
    'label' => 'Organisation type',
    'required' => TRUE,
    'default_value' => [['value' => 'Non-profit']],
  ])->save();
  echo "created field    field_organisation_type on project\n";
}

// One-time copy from the free-text field, only into projects that don't have
// a dropdown value yet — so re-running never overrides a choice made since.
$nodeStorage = \Drupal::entityTypeManager()->getStorage('node');
$copied = 0;
foreach ($nodeStorage->loadByProperties(['type' => 'project']) as $node) {
  if (!$node->get('field_organisation_type')->isEmpty() || $node->get('field_org_type')->isEmpty()) {
    continue;
  }
  $old = strtolower((string) $node->get('field_org_type')->value);
  $node->set('field_organisation_type', str_contains($old, 'for') && !str_contains($old, 'non') ? 'For-profit' : 'Non-profit');
  $node->save();
  $copied++;
}
if ($copied) {
  echo "copied organisation type into the dropdown on $copied project(s)\n";
}

$titleOverride = \Drupal\Core\Field\Entity\BaseFieldOverride::loadByName('node', 'project', 'title')
  ?? \Drupal\Core\Field\Entity\BaseFieldOverride::createFromBaseFieldDefinition(
    \Drupal::service('entity_field.manager')->getBaseFieldDefinitions('node')['title'], 'project');
$titleOverride->setLabel('Organisation or company name')->save();

// [field => [label, help text, widget, weight]].
$form = [
  'field_organisation_type' => ['Organisation type', 'Is this a non-profit organisation or a company?', 'options_select', 1],
  'field_country_ref' => ['Country', 'Start typing and pick the country from the list.', 'entity_reference_autocomplete', 2],
  'field_image' => ['Photo', 'Shown on the project card and at the top of the project page. Landscape photos work best.', 'image_image', 3],
  'field_website' => ['Website link', 'The full address, for example https://www.example.org', 'string_textfield', 4],
  'field_excerpt' => ['Description', 'Separate paragraphs with an empty line. The first paragraph is the summary shown on the project card.', 'string_textarea', 5],
  'field_trees' => ['Target: trees to be grown', 'The commitment, for example 250,000', 'string_textfield', 10],
  'field_hectares' => ['Target: hectares to be restored', 'The commitment, for example 1,200', 'string_textfield', 11],
  'field_jobs' => ['Target: jobs to be created', 'The commitment, for example 300', 'string_textfield', 12],
  'field_trees_done' => ['Delivered so far: trees grown', 'Leave empty until real reported figures exist — the site then shows progress against the target.', 'string_textfield', 20],
  'field_hectares_done' => ['Delivered so far: hectares restored', 'Leave empty until real reported figures exist.', 'string_textfield', 21],
  'field_jobs_done' => ['Delivered so far: jobs created', 'Leave empty until real reported figures exist.', 'string_textfield', 22],
  'field_slug' => ['URL slug', 'The project page address, e.g. my-organisation becomes /projects/my-organisation. Leave empty to generate it from the name.', 'string_textfield', 30],
  'field_weight' => ['Order', 'Lower numbers are listed first.', 'number', 31],
];
foreach ($form as $name => [$label, $help, $widget, $w]) {
  $config = FieldConfig::loadByName('node', 'project', $name);
  if (!$config) {
    continue;
  }
  $config->setLabel($label)->setDescription($help);
  // Every page and filter on the site groups projects by country.
  if ($name === 'field_country_ref') {
    $config->setRequired(TRUE);
  }
  $config->save();
  $formDisplay->setComponent($name, ['type' => $widget, 'weight' => $w] + ($widget === 'string_textarea' ? ['settings' => ['rows' => 10]] : []));
}
$formDisplay->setComponent('title', ['type' => 'string_textfield', 'weight' => 0]);

foreach (['field_org_type', 'field_cohort', 'field_body', 'field_category', 'field_communities', 'field_funder', 'field_funding', 'field_progress', 'field_result', 'field_status'] as $superseded) {
  if ($formDisplay->getComponent($superseded)) {
    $formDisplay->removeComponent($superseded);
    echo "hidden from form $superseded\n";
  }
}

$formDisplay->save();
echo "\nDone. Export config with `drush config:export` to capture these in config/sync.\n";
