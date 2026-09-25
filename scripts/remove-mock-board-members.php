<?php

$storage = \Drupal::entityTypeManager()->getStorage('node');
foreach ($storage->loadByProperties(['type' => 'team_member']) as $node) {
  if (str_starts_with($node->getTitle(), '[MOCK] ')) {
    print "Removed {$node->getTitle()}\n";
    $node->delete();
  }
}