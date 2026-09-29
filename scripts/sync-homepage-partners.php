<?php

/**
 * @file
 * Synchronizes homepage partners from seed/content.json, including ordering.
 *
 * Run with: vendor/bin/drush php:script scripts/sync-homepage-partners.php
 */

use Drupal\node\Entity\Node;

$data = json_decode(file_get_contents(__DIR__ . '/../seed/content.json'), TRUE, flags: JSON_THROW_ON_ERROR);
$storage = \Drupal::entityTypeManager()->getStorage('node');
$desired = [];

foreach ($data['partners'] ?? [] as $partner) {
  $name = trim((string) ($partner['name'] ?? ''));
  if ($name === '') {
    continue;
  }
  $desired[$name] = (int) ($partner['weight'] ?? 0);
  $matches = $storage->loadByProperties(['type' => 'partner', 'title' => $name]);
  $node = $matches ? reset($matches) : Node::create(['type' => 'partner']);
  $node->setTitle($name);
  $node->set('field_weight', $desired[$name]);
  $node->setPublished();
  $node->save();
}

$ids = $storage->getQuery()->accessCheck(FALSE)->condition('type', 'partner')->condition('status', 1)->execute();
foreach ($storage->loadMultiple($ids) as $node) {
  if (!array_key_exists($node->label(), $desired)) {
    $node->setUnpublished();
    $node->save();
  }
}

echo 'Synchronized ' . count($desired) . " homepage partner records.\n";
