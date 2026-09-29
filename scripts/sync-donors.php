<?php

/**
 * @file
 * Reconciles the published donor logos and ordering with seed/content.json.
 *
 * Run with: vendor/bin/drush php:script scripts/sync-donors.php
 */

use Drupal\file\Entity\File;
use Drupal\node\Entity\Node;
use Drupal\Core\File\FileSystemInterface;

$data = json_decode(file_get_contents(__DIR__ . '/../seed/content.json'), TRUE, flags: JSON_THROW_ON_ERROR);
$nodes = \Drupal::entityTypeManager()->getStorage('node');
$files = \Drupal::entityTypeManager()->getStorage('file');
$fileSystem = \Drupal::service('file_system');
$desired = [];

foreach ($data['donors'] ?? [] as $weight => $donor) {
  $title = trim((string) ($donor['name'] ?? ''));
  $filename = basename((string) ($donor['logo'] ?? ''));
  if ($title === '' || $filename === '') {
    continue;
  }
  $desired[$title] = TRUE;
  $source = __DIR__ . '/../seed/gallery/' . $filename;
  if (!is_file($source)) {
    throw new RuntimeException("Missing donor logo: $source");
  }

  $uri = 'public://gallery/' . $filename;
  $directory = 'public://gallery';
  $fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);
  $fileSystem->saveData(file_get_contents($source), $uri, FileSystemInterface::EXISTS_REPLACE);
  $fileEntities = $files->loadByProperties(['uri' => $uri]);
  $file = $fileEntities ? reset($fileEntities) : File::create(['uri' => $uri]);
  $file->set('status', 1);
  $file->save();

  $matches = $nodes->loadByProperties(['type' => 'donor', 'title' => $title]);
  $node = $matches ? reset($matches) : Node::create(['type' => 'donor']);
  $node->setTitle($title);
  $node->set('field_image', $file->id());
  $node->set('field_website', (string) ($donor['website'] ?? ''));
  $node->set('field_weight', (int) $weight);
  $node->setPublished();
  $node->save();
}

$ids = $nodes->getQuery()->accessCheck(FALSE)->condition('type', 'donor')->condition('status', 1)->execute();
foreach ($nodes->loadMultiple($ids) as $node) {
  if (!isset($desired[$node->label()])) {
    $node->setUnpublished();
    $node->save();
  }
}

echo 'Synchronized ' . count($desired) . " donor records.\n";
