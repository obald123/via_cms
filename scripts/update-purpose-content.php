<?php

$updates = [
  'A Restored Africa Where People and Nature Thrive Together' => 'VIA Foundation envisions thriving landscapes that generate economic opportunity, strengthen communities, protect biodiversity, and contribute to climate resilience across the continent.',
  'Accelerating Locally Led Restoration Across Africa' => 'VIA Foundation mobilizes finance, technical support, strategic partnerships, and innovative financial solutions that enable restoration champions to deliver measurable environmental and social impact.',
];

$storage = \Drupal::entityTypeManager()->getStorage('node');
foreach ($storage->loadByProperties(['type' => 'purpose_statement']) as $node) {
  $body = $updates[$node->getTitle()] ?? NULL;
  if ($body === NULL) {
    continue;
  }

  $node->set('field_desc', $body)->save();
  print "Updated {$node->getTitle()}\n";
}