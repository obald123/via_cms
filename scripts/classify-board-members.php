<?php

$boardMembers = [
  'Donald Kaberuka',
  'Mamadou Diakhité',
  'Erik Solheim',
  'Rebekah Shirley',
];

$storage = \Drupal::entityTypeManager()->getStorage('node');
foreach ($storage->loadByProperties(['type' => 'team_member']) as $node) {
  if (!in_array($node->getTitle(), $boardMembers, TRUE)) {
    continue;
  }

  $node->set('field_member_type', 'board')->save();
  print "Classified {$node->getTitle()} as board\n";
}