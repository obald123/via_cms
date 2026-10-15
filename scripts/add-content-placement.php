<?php

/**
 * @file
 * Adds website page/section metadata and filters to the Content overview.
 *
 * Additive and idempotent. Run with:
 *   ./vendor/bin/drush php:script scripts/add-content-placement.php
 */

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\views\Entity\View;

$pages = [
  'Home' => ['Homepage', 'Impact statistics', 'Trusted partners', 'Restoration photos', 'Featured projects', 'Call to action', 'Team members', 'Board members'],
  'About' => ['Overview', 'Vision and mission', 'Our approach', 'Team', 'Board', 'Donors'],
  'Portfolio' => ['Projects', 'Project profiles'],
  'Offers' => ['What We Do', 'Where We Work', 'How We Partner'],
  'Impact' => ['Impact summary', 'Map and charts', 'Monitoring, Reporting and Verification'],
  'News' => ['News', 'Stories', 'Publications'],
  'Careers' => ['Job postings'],
  'Gallery' => ['Photos', 'Videos'],
  'Partner' => ['Partner logos', 'Partner enquiries'],
  'Other' => ['Policies', 'Sitewide content', 'Other'],
];

$ensureVocabulary = static function (string $id, string $label): void {
  if (!Vocabulary::load($id)) {
    Vocabulary::create(['vid' => $id, 'name' => $label])->save();
  }
};
$ensureVocabulary('website_page', 'Website page');
$ensureVocabulary('website_section', 'Website section');

$ensureStorage = static function (string $entityType, string $name, string $targetType, int $cardinality = 1): void {
  $storage = FieldStorageConfig::loadByName($entityType, $name);
  if (!$storage) {
    FieldStorageConfig::create([
      'field_name' => $name,
      'entity_type' => $entityType,
      'type' => 'entity_reference',
      'cardinality' => $cardinality,
      'settings' => ['target_type' => $targetType],
    ])->save();
  }
  elseif ($storage->getCardinality() !== $cardinality) {
    $storage->setCardinality($cardinality)->save();
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
$pageWeight = 0;
foreach (array_keys($pages) as $name) {
  $matches = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => 'website_page', 'name' => $name]);
  $pageTerms[$name] = $matches ? reset($matches) : Term::create(['vid' => 'website_page', 'name' => $name]);
  $pageTerms[$name]->setWeight($pageWeight++)->save();
}

$sectionTerms = [];
$sectionWeight = 0;
foreach ($pages as $pageName => $sections) {
  foreach ($sections as $sectionName) {
    $matches = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => 'website_section', 'name' => $sectionName]);
    $section = $matches ? reset($matches) : Term::create(['vid' => 'website_section', 'name' => $sectionName]);
    $section->set('field_parent_page', $pageTerms[$pageName]->id())
      ->setWeight($sectionWeight++)
      ->save();
    $sectionTerms[$pageName][$sectionName] = $section;
  }
}

$ensureNodeField = static function (string $fieldName, string $label, string $vocabulary) use ($ensureStorage): void {
  $ensureStorage('node', $fieldName, 'taxonomy_term', FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED);

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

$assignPlacement = static function (NodeInterface $node, array $placements) use ($pageTerms, $sectionTerms): void {
  $pages = [];
  $sections = [];
  foreach ($placements as [$pageName, $sectionName]) {
    $pageId = (int) $pageTerms[$pageName]->id();
    $sectionId = (int) $sectionTerms[$pageName][$sectionName]->id();
    $pages[$pageId] = ['target_id' => $pageId];
    $sections[$sectionId] = ['target_id' => $sectionId];
  }
  $node->set('field_website_page', array_values($pages));
  $node->set('field_website_section', array_values($sections));
  $node->save();
};

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
    if ($bundle === 'partner') {
      $assignPlacement($node, [
        ['Home', 'Trusted partners'],
        ['Partner', 'Partner logos'],
      ]);
      continue;
    }
    if ($bundle === 'team_member') {
      $aboutSection = ($node->get('field_member_type')->value ?? '') === 'board' ? 'Board' : 'Team';
      $homeSection = $aboutSection === 'Board' ? 'Board members' : 'Team members';
      $assignPlacement($node, [
        ['Home', $homeSection],
        ['About', $aboutSection],
      ]);
      continue;
    }
    if ($bundle === 'gallery_item' && ($node->get('field_slug')->value ?? '') === 'dji-0123-forest-hills') {
      $assignPlacement($node, [
        ['Gallery', 'Photos'],
        ['Impact', 'Monitoring, Reporting and Verification'],
        ['About', 'Overview'],
      ]);
      continue;
    }
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
    elseif ($bundle === 'gallery_item') {
      $sectionName = ($node->get('field_media_type')->value ?? '') === 'video' ? 'Videos' : 'Photos';
    }
    $assignPlacement($node, [[$pageName, $sectionName]]);
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