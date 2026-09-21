<?php

/**
 * @file
 * Runs one Airtable → project sync immediately, without waiting for a
 * webhook ping or the daily cron safety net. See AirtableSync for the field
 * mapping and what stays staff-owned.
 *
 * Use this the first time the integration is set up: it's what links the
 * ~115 project profiles seeded from data.ts to their Airtable row (matched
 * once by title, then by field_airtable_uuid from then on), so run it once
 * right after add-airtable-sync-fields.php and before relying on the
 * webhook, and check the "linked" count looks right.
 *
 * Requires $settings['via_airtable_token'], via_airtable_base_id and
 * via_airtable_table_id in settings.php — see AirtableSync::fromSettings().
 *
 *   drush php:script scripts/sync-airtable-projects.php
 */

use Drupal\via_api\AirtableSync;

$sync = AirtableSync::fromSettings();
if (!$sync) {
  echo "! via_airtable_token / via_airtable_base_id / via_airtable_table_id are not set in settings.php — see AirtableSync::fromSettings(). Nothing to do.\n";
  return;
}

$result = $sync->sync();
echo "Airtable rows seen:     {$result['total']}\n";
echo "Project nodes created:  {$result['created']}\n";
echo "Project nodes updated:  {$result['updated']}\n";
echo "Newly linked by title:  {$result['linked']}\n";
