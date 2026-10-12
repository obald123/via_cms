<?php

/**
 * @file
 * Adds website page/section metadata and filters to the Content overview.
 *
 * Additive and idempotent. Run with:
 *   ./vendor/bin/drush php:script scripts/add-content-placement.php
 */

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\NodeType;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\views\Entity\View;

$pages = [
  'Home' => ['Homepage', 'Impact statistics', 'Trusted partners', 'Restoration photos', 'Featured projects', 'Call to action'],
  'About' => ['Overview', 'Vision and mission', 'Our approach', 'Team', 'Board', 'Donors'],
  'Portfolio' => ['Projects', 'Project profiles'],
  'Offers' => ['What We Do', 'Where We Work', 'How We Partner'],
  'Impact' => ['Impact summary', 'Map and charts', 'Monitoring, Reporting and Verification'],
  'News' => ['News', 'Stories', 'Publications'],
  'Careers' => ['Job postings'],
  'Gallery' => ['Photos', 'Videos'],
  'Other' => ['Partner enquiries', 'Policies', 'Sitewide content', 'Other'],
];

$ensureVocabulary = static function (string $id, string $label): void {
  if (!Vocabulary::load($id)) {
    Vocabulary::create(['vid' => $id, 'name' => $label])->save();
  }
};
$ensureVocabulary('website_page', 'Website page');
$ensureVocabulary('website_section', 'Website section');

$ensureStorage = static function (string $entityType, string $name, string $targetType): void {
  if (!FieldStorageConfig::loadByName($entityType, $name)) {
    FieldStorageConfig::create([
      'field_name' => $name,
      'entity_type' => $entityType,
      'type' => 'entity_reference',
      'cardinality' => 1,
      'settings' => ['target_type' => $targetType],
    ])->save();
  }
};

$ensureStorage('taxonomy_term', 'field_parent_page', 'taxonomy_term');
if (!FieldConfig::loadByName('taxonomy_term', 'website_section', 'field_parent_page')) {
  FieldConfig::create([
    'field_name' => 'field_parent_page',
    'entity_type' => 'taxonomy_term',
    'bundle' => 'website_section',
    'label' => 'Website page',
    'settings' => [
      'handler' => 'default:taxonomy_term',
      'handler_settings' => ['target_bundles' => ['website_page' => 'website_page']],
    ],
  ])->save();
}
$termForm = \Drupal::service('entity_display.repository')->getFormDisplay('taxonomy_term', 'website_section', 'default');
$termForm->setComponent('field_parent_page', ['type' => 'options_select', 'weight' => 0])->save();

$pageTerms = [];
foreach (array_keys($pages) as $name) {
  $matches = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => 'website_page', 'name' => $name]);
  $pageTerms[$name] = $matches ? reset($matches) : Term::create(['vid' => 'website_page', 'name' => $name]);
  if (!$pageTerms[$name]->id()) {
    $pageTerms[$name]->save();
  }
}

$sectionTerms = [];
foreach ($pages as $pageName => $sections) {
  foreach ($sections as $sectionName) {
    $matches = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => 'website_section', 'name' => $sectionName]);
    $section = $matches ? reset($matches) : Term::create(['vid' => 'website_section', 'name' => $sectionName]);
    $section->set('field_parent_page', $pageTerms[$pageName]->id())->save();
    $sectionTerms[$pageName][$sectionName] = $section;
  }
}

$ensureNodeField = static function (string $fieldName, string $label, string $vocabulary): void {
  $ensureStorage = static function () use ($fieldName): void {
    if (!FieldStorageConfig::loadByName('node', $fieldName)) {
      FieldStorageConfig::create([
        'field_name' => $fieldName,
        'entity_type' => 'node',
        'type' => 'entity_reference',
        'cardinality' => 1,
        'settings' => ['target_type' => 'taxonomy_term'],
      ])->save();
    }
  };
  $ensureStorage();

  foreach (NodeType::loadMultiple() as $bundle => $type) {
    if (!FieldConfig::loadByName('node', $bundle, $fieldName)) {
      FieldConfig::create([
        'field_name' => $fieldName,
        'entity_type' => 'node',
        'bundle' => $bundle,
        'label' => $label,
        'settings' => [
          'handler' => 'default:taxonomy_term',
          'handler_settings' => ['target_bundles' => [$vocabulary => $vocabulary]],
        ],
      ])->save();
    }
    $form = \Drupal::service('entity_display.repository')->getFormDisplay('node', $bundle, 'default');
    $form->setComponent($fieldName, ['type' => 'options_select', 'weight' => 90])->save();
  }
};
$ensureNodeField('field_website_page', 'Website page', 'website_page');
$ensureNodeField('field_website_section', 'Website section', 'website_section');

$placement = [
  'hero_stat' => ['Home', 'Homepage'],
  'impact_card' => ['Impact', 'Impact summary'],
  'partner' => ['Home', 'Trusted partners'],
  'restoration_photo' => ['Home', 'Restoration photos'],
  'project' => ['Portfolio', 'Projects'],
  'service' => ['Offers', 'What We Do'],
  'priority_landscape' => ['Offers', 'Where We Work'],
  'implementation_partnership' => ['Offers', 'How We Partner'],
  'country' => ['Impact', 'Map and charts'],
  'news' => ['News', 'News'],
  'story' => ['News', 'Stories'],
  'job_posting' => ['Careers', 'Job postings'],
  'purpose_statement' => ['About', 'Vision and mission'],
  'pillar' => ['About', 'Our approach'],
  'donor' => ['About', 'Donors'],
  'gallery_item' => ['Gallery', 'Photos'],
];

$nodeStorage = \Drupal::entityTypeManager()->getStorage('node');
foreach (NodeType::loadMultiple() as $bundle => $type) {
  foreach ($nodeStorage->loadByProperties(['type' => $bundle]) as $node) {
    if ($bundle === 'gallery_item' && $node->hasField('field_landscape') && $node->get('field_landscape')->isEmpty()) {
      $landscapeBySlug = [
        'dji-0123-forest-hills' => 'Kenya’s Greater Rift Valley',
        'dji-0154-waterfall-aerial' => 'Lake Kivu & Rusizi Basin',
      ];
      $landscapeName = $landscapeBySlug[$node->get('field_slug')->value ?? ''] ?? '';
      if ($landscapeName !== '') {
        $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => 'landscape', 'name' => $landscapeName]);
        if ($terms) {
          $node->set('field_landscape', (int) reset($terms)->id())->save();
        }
      }
    }
    if (!$node->get('field_website_page')->isEmpty() || !$node->get('field_website_section')->isEmpty()) {
      continue;
    }
    [$pageName, $sectionName] = $placement[$bundle] ?? ['Other', 'Other'];
    if ($bundle === 'page_hero') {
      $slug = (string) ($node->get('field_slug')->value ?? '');
      [$pageName, $sectionName] = match ($slug) {
        'about' => ['About', 'Overview'],
        'team' => ['About', 'Team'],
        'projects' => ['Portfolio', 'Projects'],
        default => ['Other', 'Sitewide content'],
      };
    }
    elseif ($bundle === 'team_member') {
      $sectionName = ($node->get('field_member_type')->value ?? '') === 'board' ? 'Board' : 'Team';
      $pageName = 'About';
    }
    elseif ($bundle === 'gallery_item') {
      $sectionName = ($node->get('field_media_type')->value ?? '') === 'video' ? 'Videos' : 'Photos';
      if (($node->get('field_slug')->value ?? '') === 'dji-0123-forest-hills') {
        $pageName = 'Impact';
        $sectionName = 'Monitoring, Reporting and Verification';
      }
    }
    $node->set('field_website_page', $pageTerms[$pageName]->id());
    $node->set('field_website_section', $sectionTerms[$pageName][$sectionName]->id());
    $node->save();
  }
}

$view = View::load('content');
if ($view) {
  $displays = $view->get('display');
  $filters = $displays['default']['display_options']['filters'] ?? [];
  $makeFilter = static function (string $field, string $label, string $identifier, string $vocabulary): array {
    $targetField = $field . '_target_id';
    return [
      'id' => $targetField,
      'table' => 'node__' . $field,
      'field' => $targetField,
      'relationship' => 'none',
      'group_type' => 'group',
      'admin_label' => '',
      'plugin_id' => 'taxonomy_index_tid',
      'operator' => 'or',
      'value' => [],
      'group' => 1,
      'exposed' => TRUE,
      'expose' => [
        'operator_id' => $identifier . '_op',
        'label' => $label,
        'description' => '',
        'use_operator' => FALSE,
        'operator' => $identifier . '_op',
        'operator_limit_selection' => FALSE,
        'operator_list' => [],
        'identifier' => $identifier,
        'required' => FALSE,
        'remember' => FALSE,
        'multiple' => FALSE,
        'remember_roles' => ['authenticated' => 'authenticated', 'anonymous' => '0', 'administrator' => '0'],
        'reduce' => FALSE,
      ],
      'is_grouped' => FALSE,
      'sub_handler' => 'default:taxonomy_term',
      'sub_handler_settings' => [
        'target_bundles' => [$vocabulary => $vocabulary],
        'sort' => ['field' => '_none', 'direction' => 'ASC'],
        'auto_create' => FALSE,
      ],
      'widget' => 'select',
      'reduce_duplicates' => FALSE,
      'type' => 'select',
      'limit' => TRUE,
      'vid' => $vocabulary,
      'hierarchy' => FALSE,
      'error_message' => TRUE,
      'group_info' => [
        'label' => '', 'description' => '', 'identifier' => '', 'optional' => TRUE,
        'widget' => 'select', 'multiple' => FALSE, 'remember' => FALSE,
        'default_group' => 'All', 'default_group_multiple' => [], 'group_items' => [],
      ],
    ];
  };
  $filters = [
    'site_page' => $filters['site_page'] ?? $makeFilter('field_website_page', 'Website page', 'site_page', 'website_page'),
    'site_section' => $filters['site_section'] ?? $makeFilter('field_website_section', 'Website section', 'site_section', 'website_section'),
  ] + $filters;
  $displays['default']['display_options']['filters'] = $filters;
  $view->set('display', $displays)->save();
}

echo "Website placement fields and Content overview filters are ready.\n";