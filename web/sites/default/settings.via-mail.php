<?php

/**
 * @file
 * Outgoing email through Gmail SMTP with an app password.
 *
 * Included from settings.php, which holds the credentials (settings.php is
 * gitignored, so they are never committed):
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
 * With either credential empty nothing is overridden and Drupal keeps its
 * default mailer. Check the setup with: drush php:script scripts/test-mail.php
 */

$via_gmail_user = trim((string) ($settings['via_gmail_user'] ?? ''));
$via_gmail_password = str_replace(' ', '', (string) ($settings['via_gmail_app_password'] ?? ''));

if ($via_gmail_user !== '' && $via_gmail_password !== '') {
  // Core's Symfony mailer; webform mail goes the same way.
  $config['system.mail']['interface']['default'] = 'symfony_mailer';
  $config['system.mail']['interface']['webform'] = 'symfony_mailer';
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

unset($via_gmail_user, $via_gmail_password);
