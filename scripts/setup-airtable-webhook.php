<?php

/**
 * @file
 * Registers this environment's Airtable webhook (or refreshes it if one is
 * already registered) so a change in Airtable pushes to
 * /api/v1/airtable-webhook in real time, instead of waiting for the daily
 * cron safety net.
 *
 * Only run this against an environment Airtable can actually reach —
 * production, not local XAMPP, since Airtable needs a public HTTPS URL to
 * call. Local dev still gets the data through the daily cron safety-net
 * sync (see via_api_cron() in via_api.module) or a manual run of
 * scripts/sync-airtable-projects.php.
 *
 * Requires $settings['via_airtable_notification_url'] set in settings.php
 * to this environment's own airtable-webhook URL, e.g.
 * 'https://viacms.dtecsoftwaresolutions.com/api/v1/airtable-webhook'.
 * Idempotent — re-running refreshes the existing webhook rather than making
 * a second one.
 *
 *   drush php:script scripts/setup-airtable-webhook.php
 */

use Drupal\Core\Site\Settings;
use Drupal\via_api\AirtableSync;

$sync = AirtableSync::fromSettings();
if (!$sync) {
  echo "! via_airtable_token / via_airtable_base_id / via_airtable_table_id are not set in settings.php. Nothing to do.\n";
  return;
}

$notificationUrl = trim((string) Settings::get('via_airtable_notification_url', ''));
if ($notificationUrl === '') {
  echo "! \$settings['via_airtable_notification_url'] is not set in settings.php — set it to this environment's public "
    . "/api/v1/airtable-webhook URL before running this on a machine Airtable can reach.\n";
  return;
}

$result = $sync->createOrRefreshWebhook($notificationUrl);
echo "Webhook {$result['status']}: id {$result['id']}\n";
echo "Notification URL: $notificationUrl\n";
echo "\nCron (via_api_cron) will keep refreshing this automatically from now on — no need to re-run this script unless "
  . "the webhook is deleted from the Airtable side.\n";
