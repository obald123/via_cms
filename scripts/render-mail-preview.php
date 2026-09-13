<?php

/**
 * @file
 * Dumps each email's rendered HTML to disk, for a visual check without
 * sending anything — see ViaMail::spec()/ViaEmailTemplate::render(). The
 * embedded-image cid:via-logo reference won't resolve when the file is opened
 * directly, so this rewrites it to a file:// path to the same PNG purely for
 * the preview.
 *
 *   drush php:script scripts/render-mail-preview.php -- /path/to/out-dir
 */

use Drupal\via_api\Email\ViaEmailTemplate;
use Drupal\via_api\ViaMail;

$outDir = rtrim($extra[0] ?? sys_get_temp_dir(), '/\\');
if (!is_dir($outDir)) {
  mkdir($outDir, 0755, TRUE);
}

$logoFileUrl = 'file:///' . str_replace('\\', '/', realpath(ViaMail::logoPath()));

$samples = [
  'partner_enquiry_notify' => ['name' => 'Amina Njau', 'email' => 'amina.njau@example.org', 'organization' => 'Kilosa Women\'s Restoration Cooperative', 'message' => "Hello,\n\nWe'd like to explore a technical assistance grant for our nursery expansion. Could someone from your team reach out?\n\nThanks,\nAmina"],
  'partner_enquiry_ack' => ['name' => 'Amina Njau', 'message' => "Hello,\n\nWe'd like to explore a technical assistance grant for our nursery expansion. Could someone from your team reach out?\n\nThanks,\nAmina"],
  'newsletter_welcome' => [],
  'newsletter_notify' => ['email' => 'subscriber@example.org'],
  'test' => [],
];

foreach ($samples as $key => $params) {
  $spec = ViaMail::spec($key, $params);
  $html = str_replace('cid:via-logo', $logoFileUrl, ViaEmailTemplate::render($spec));
  $path = "$outDir/$key.html";
  file_put_contents($path, $html);
  echo "wrote $path\n";
}
