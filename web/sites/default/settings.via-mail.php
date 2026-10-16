<?php

/**
 * @file
 * Outgoing email: Microsoft Graph first, Gmail SMTP as the fallback.
 *
 * Included from settings.php, which holds the credentials (settings.php is
 * gitignored, so they are never committed). The $settings lines must come
 * BEFORE the include — this file reads them at include time.
 *
 * Microsoft Graph (production — VIA's Microsoft 365 tenant, through the Entra
 * ID app "Website Contact US", OAuth2 client credentials, Mail.Send):
 *
 *   $settings['via_graph_tenant_id'] = '…';
 *   $settings['via_graph_client_id'] = '…';
 *   $settings['via_graph_client_secret'] = '…';
 *   $settings['via_graph_sender'] = 'mailbox@via-foundation.org';
 *
 * With all four set, \Drupalia_api\Mail\GraphTransport sends every email
 * over HTTPS as that mailbox (no SMTP at all) and the Gmail block below is
 * ignored. The sender is also the From address and, unless via_notify_email
 * says otherwise, the inbox that receives VIA's notifications.
 *
 * Gmail SMTP (fallback, used only when any Graph value is empty):
 *
 *   $settings['via_gmail_user'] = 'someone@gmail.com';
 *   $settings['via_gmail_app_password'] = 'abcd efgh ijkl mnop';
 *   $settings['via_notify_email'] = '';   // optional, see below
 *   include __DIR__ . '/settings.via-mail.php';
 *
 * The app password is the 16-character one Google generates under
 * Google Account → Security → 2-Step Verification → App passwords — not the
 * account's normal password, which Gmail refuses over SMTP. Spaces in it are
 * ignored.
 *
 * via_notify_email is the inbox that receives Partner With Us messages and new
 * subscriber alerts; empty means the Gmail account itself. Gmail always sends
 * as the authenticated account, so that address is also the From address.
 *
 * With neither Graph nor both Gmail credentials set, nothing is overridden and
 * Drupal keeps its default (sendmail) transport. Check the setup with: drush php:script scripts/test-mail.php
 */

$via_graph_sender = trim((string) ($settings['via_graph_sender'] ?? ''));
$via_graph_ready = $via_graph_sender !== ''
  && trim((string) ($settings['via_graph_tenant_id'] ?? '')) !== ''
  && trim((string) ($settings['via_graph_client_id'] ?? '')) !== ''
  && trim((string) ($settings['via_graph_client_secret'] ?? '')) !== '';

if ($via_graph_ready) {
  // The transport itself is chosen in ViaHtmlMailer::getMailer() from these
  // same settings; only the site address needs setting here.
  $config['system.site']['mail'] = $via_graph_sender;
}

$via_gmail_user = trim((string) ($settings['via_gmail_user'] ?? ''));
$via_gmail_password = str_replace(' ', '', (string) ($settings['via_gmail_app_password'] ?? ''));

if (!$via_graph_ready && $via_gmail_user !== '' && $via_gmail_password !== '') {
  // Which mail PLUGIN sends the message ('via_html_mailer', VIA's own branded
  // one) is set in Drupal config by build-content-model.php, not here — a
  // settings.php override on 'interface' would beat that config value on
  // every request, CLI included, with no visible sign anything was wrong
  // (drush config:get shows the stored value, not what settings.php
  // overrides — that's exactly how an earlier version of this file forced
  // core's plain-text "symfony_mailer" plugin silently, for weeks, even
  // after via_html_mailer was built and switched on in config). This file
  // only ever sets the transport (mailer_dsn) below, which does have to be a
  // settings.php override, since it carries a secret.
  //
  // Port 587 — Symfony upgrades the connection with STARTTLS.
  $config['system.mail']['mailer_dsn'] = [
    'scheme' => 'smtp',
    'host' => 'smtp.gmail.com',
    'user' => $via_gmail_user,
    'password' => $via_gmail_password,
    'port' => 587,
    'options' => [],
  ];
  $config['system.site']['mail'] = $via_gmail_user;
}

unset($via_graph_sender, $via_graph_ready, $via_gmail_user, $via_gmail_password);
