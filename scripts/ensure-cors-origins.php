<?php

/**
 * @file
 * Ensures the production frontend domains are in the environment CORS list.
 *
 * The active services.yml is environment-local and is intentionally not
 * committed. Run this before rebuilding Drupal's service container.
 */

$servicesPath = DRUPAL_ROOT . '/sites/default/services.yml';
if (!is_file($servicesPath)) {
  throw new RuntimeException("Missing environment services file: $servicesPath");
}

$contents = file_get_contents($servicesPath);
if ($contents === FALSE) {
  throw new RuntimeException("Could not read environment services file: $servicesPath");
}

$lineEnding = str_contains($contents, "\r\n") ? "\r\n" : "\n";
$lines = preg_split('/\r\n|\n|\r/', $contents);
$corsStart = NULL;
$corsEnd = count($lines);
foreach ($lines as $index => $line) {
  if ($corsStart === NULL && preg_match('/^  cors\.config:\s*(?:#.*)?$/', $line)) {
    $corsStart = $index;
    continue;
  }
  if ($corsStart !== NULL && $index > $corsStart && preg_match('/^  [A-Za-z0-9_.-]+:/', $line)) {
    $corsEnd = $index;
    break;
  }
}
if ($corsStart === NULL) {
  throw new RuntimeException("Could not find cors.config in $servicesPath");
}

$originsIndex = NULL;
for ($index = $corsStart + 1; $index < $corsEnd; $index++) {
  if (preg_match('/^    allowedOrigins:\s*(.*?)\s*$/', $lines[$index], $matches)) {
    if ($matches[1] !== '' && !str_starts_with($matches[1], '#')) {
      throw new RuntimeException('Expected allowedOrigins to use a YAML block list.');
    }
    $originsIndex = $index;
    break;
  }
}
if ($originsIndex === NULL) {
  throw new RuntimeException("Could not find cors.config.allowedOrigins in $servicesPath");
}

$requiredOrigins = [
  'https://viafoundation.dtecsoftwaresolutions.com',
  'https://via-foundation.org',
  'https://www.via-foundation.org',
];
$presentOrigins = [];
for ($index = $originsIndex + 1; $index < $corsEnd; $index++) {
  if (preg_match('/^      -\s*(?:"([^"]+)"|\'([^\']+)\'|([^#\s]+))(?:\s+#.*)?\s*$/', $lines[$index], $matches)) {
    $presentOrigins[] = $matches[1] ?: ($matches[2] ?: $matches[3]);
  }
}

$missingOrigins = array_values(array_diff($requiredOrigins, $presentOrigins));
if (!$missingOrigins) {
  echo "All required frontend origins are already present in services.yml.\n";
  return;
}

$newLines = array_map(static fn(string $origin): string => "      - '$origin'", $missingOrigins);
array_splice($lines, $originsIndex + 1, 0, $newLines);
$updated = implode($lineEnding, $lines);
if (file_put_contents($servicesPath, $updated, LOCK_EX) === FALSE) {
  throw new RuntimeException("Could not update environment services file: $servicesPath");
}

foreach ($missingOrigins as $origin) {
  echo "Added CORS origin: $origin\n";
}
