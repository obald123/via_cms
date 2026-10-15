<?php

/**
 * @file
 * Sets the Drupal front-end and admin favicons to the website's VIA mark.
 *
 * The source image is committed with via_api so every environment receives
 * the same icon without relying on a manually uploaded public-files asset.
 */

use Drupal\Core\File\FileSystemInterface;

$source = DRUPAL_ROOT . '/modules/custom/via_api/assets/newlogo.png';
if (!is_readable($source)) {
  throw new RuntimeException("Missing committed favicon asset: $source");
}

$fileSystem = \Drupal::service('file_system');
$destination = 'public://via-foundation-favicon.png';
$publicDirectory = 'public://';
if (!$fileSystem->prepareDirectory($publicDirectory, FileSystemInterface::CREATE_DIRECTORY)) {
  throw new RuntimeException('Could not prepare Drupal public files for the favicon.');
}
$bytes = file_get_contents($source);
if ($bytes === FALSE) {
  throw new RuntimeException("Could not read favicon asset: $source");
}
$saved = $fileSystem->saveData($bytes, $destination, FileSystemInterface::EXISTS_REPLACE);
if ($saved === FALSE) {
  throw new RuntimeException("Could not write favicon asset to $destination");
}

$adminTheme = \Drupal::config('system.theme')->get('admin') ?: 'claro';
$configNames = array_unique(['system.theme.global', $adminTheme . '.settings']);
foreach ($configNames as $configName) {
  \Drupal::configFactory()->getEditable($configName)
    ->set('features.favicon', TRUE)
    ->set('favicon.path', $destination)
    ->set('favicon.mimetype', 'image/png')
    ->set('favicon.use_default', FALSE)
    ->save();
}

echo "Drupal site and admin favicon set to $destination.\n";
