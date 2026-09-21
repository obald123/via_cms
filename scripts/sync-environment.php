<?php

/**
 * @file
 * Makes this Drupal match another one that's already set up correctly (local
 * dev, in practice) — for things that a normal `git push` + deploy.sh never
 * carries across on their own:
 *
 * 1. settings.php's `file_private_path`. It's gitignored on purpose — Drupal
 *    convention keeps environment-specific paths out of version control —
 *    so a fresh environment never gets it from a deploy. Without it, the
 *    private:// stream wrapper never registers and every résumé/whistleblower
 *    attachment upload fails (see CLAUDE.md's stream-wrapper note). Adds the
 *    line if it's missing, and creates the directory it points at.
 * 1b/1c. The empty settings.php placeholder lines for Gmail outgoing mail and
 *    the Airtable project sync. Both are secrets, so only the blank lines are
 *    added here — the values themselves are filled in by hand per
 *    environment, never copied.
 * 2. Real content that only ever exists in one database because a person
 *    (or an earlier `drush php:eval`) put it there directly rather than it
 *    coming through the data.ts → seed-content.php pipeline. Currently just
 *    the one live job posting.
 *
 * Idempotent — checks before writing either, so it's safe to run on an
 * environment that already has both, and safe to fold into deploy.sh so
 * environments never drift apart on these two things again.
 *
 *   drush php:script scripts/sync-environment.php
 */

use Drupal\node\Entity\Node;

/* ── 1. settings.php: file_private_path ──────────────────────────────── */
$settingsPath = DRUPAL_ROOT . '/sites/default/settings.php';
$contents = file_get_contents($settingsPath);

if ($contents === FALSE) {
  echo "! could not read $settingsPath — add \$settings['file_private_path'] by hand, see CLAUDE.md\n";
}
elseif (str_contains($contents, 'file_private_path')) {
  echo "settings.php already sets file_private_path — left alone\n";
}
else {
  // Drupal ships this file read-only after install; put the original
  // permissions back once the write is done rather than leaving it open.
  $originalPerms = fileperms($settingsPath) & 0777;
  chmod($settingsPath, 0644);
  $addition = "\n// Added by scripts/sync-environment.php — see CLAUDE.md.\n"
    . "\$settings['file_private_path'] = dirname(DRUPAL_ROOT) . '/private-files';\n";
  file_put_contents($settingsPath, $contents . $addition);
  chmod($settingsPath, $originalPerms);
  echo "added file_private_path to settings.php\n";
}

$privateDir = dirname(DRUPAL_ROOT) . '/private-files';
if (!is_dir($privateDir)) {
  mkdir($privateDir, 0755, TRUE);
  echo "created $privateDir\n";
}
else {
  echo "$privateDir already exists\n";
}

/* ── 1b. settings.php: Gmail outgoing email ───────────────────────────── */
// Adds empty credential lines and the include of settings.via-mail.php. The
// values themselves are secrets, so they are never copied between
// environments — fill them in by hand in each settings.php.
$contents = file_get_contents($settingsPath);
if ($contents !== FALSE && !str_contains($contents, 'settings.via-mail.php')) {
  $originalPerms = fileperms($settingsPath) & 0777;
  chmod($settingsPath, 0644);
  $addition = "\n// Outgoing email via Gmail — fill in, see settings.via-mail.php. Added by scripts/sync-environment.php.\n"
    . "\$settings['via_gmail_user'] = '';\n"
    . "\$settings['via_gmail_app_password'] = '';\n"
    . "\$settings['via_notify_email'] = '';\n"
    . "include __DIR__ . '/settings.via-mail.php';\n";
  file_put_contents($settingsPath, $contents . $addition);
  chmod($settingsPath, $originalPerms);
  echo "added the Gmail email block to settings.php — fill in the credentials\n";
}
elseif ($contents !== FALSE) {
  echo "settings.php already includes settings.via-mail.php — left alone\n";
}

/* ── 1c. settings.php: Airtable project sync ──────────────────────────── */
// Same reasoning as the Gmail block above: the token is a secret, so only the
// empty placeholder lines are copied — fill in the value by hand per
// environment. via_airtable_notification_url is deliberately left blank here
// even after filling in the token: it should only ever be set on the one
// environment Airtable can reach over public HTTPS (production), never on
// local XAMPP — see scripts/setup-airtable-webhook.php.
$contents = file_get_contents($settingsPath);
if ($contents !== FALSE && !str_contains($contents, 'via_airtable_token')) {
  $originalPerms = fileperms($settingsPath) & 0777;
  chmod($settingsPath, 0644);
  $addition = "\n// Airtable project sync — fill in, see AirtableSync::fromSettings(). Added by scripts/sync-environment.php.\n"
    . "\$settings['via_airtable_token'] = '';\n"
    . "\$settings['via_airtable_base_id'] = '';\n"
    . "\$settings['via_airtable_table_id'] = '';\n"
    . "// Only set this on the environment Airtable should push webhook pings to (production) — see setup-airtable-webhook.php.\n"
    . "\$settings['via_airtable_notification_url'] = '';\n";
  file_put_contents($settingsPath, $contents . $addition);
  chmod($settingsPath, $originalPerms);
  echo "added the Airtable sync block to settings.php — fill in the token and base/table ids\n";
}
elseif ($contents !== FALSE) {
  echo "settings.php already has via_airtable_token — left alone\n";
}

/* ── 2. The one piece of content that only ever lived in one database ──── */
$existing = \Drupal::entityTypeManager()->getStorage('node')
  ->loadByProperties(['type' => 'job_posting', 'field_slug' => 'portfolio-grants-officer']);

if ($existing) {
  echo "job posting 'portfolio-grants-officer' already exists — left alone\n";
}
else {
  $n = Node::create([
    'type' => 'job_posting',
    'title' => 'Portfolio & Grants Officer',
    'field_slug' => 'portfolio-grants-officer',
    'field_department' => 'Programs',
    'field_location' => 'Kigali, Rwanda (hybrid)',
    'field_employment_type' => 'Full-time',
    'field_excerpt' => 'Support due diligence, disbursement, and monitoring across our portfolio of restoration organisations in Burundi, DRC, Ghana, Kenya and Rwanda.',
    'field_body' => [
      ['value' => "VIA Foundation is hiring a Portfolio & Grants Officer to support due diligence, disbursement, and monitoring across our portfolio of restoration organisations in Burundi, DRC, Ghana, Kenya and Rwanda."],
      ['value' => "You'll work closely with grantees through each stage of the grant cycle, from application review through to the country-level reporting that feeds into our annual impact review."],
      ['value' => 'This role reports to the Head of Programs.'],
    ],
    'field_responsibilities' => [
      ['value' => 'Review grant applications and lead due diligence on prospective grantees.'],
      ['value' => 'Track disbursement milestones against agreed restoration targets.'],
      ['value' => "Prepare the country-level reporting that feeds into VIA Foundation's annual impact review."],
      ['value' => 'Support grantees through each stage of the grant cycle.'],
    ],
    'field_requirements' => [
      ['value' => '3+ years of experience in grants management, portfolio finance, or a related field.'],
      ['value' => 'Familiarity with restoration, conservation, or development finance in Africa.'],
      ['value' => 'Comfortable working across multiple countries and time zones.'],
      ['value' => 'Based in or able to relocate to Kigali, Rwanda (hybrid role).'],
    ],
    'field_job_status' => 'Open',
    'field_published_at' => '2026-09-03',
    'field_closing_at' => '2026-09-30',
    'status' => 1,
  ]);
  $n->save();
  echo 'created job posting node ' . $n->id() . "\n";
}

echo "\nDone.\n";
