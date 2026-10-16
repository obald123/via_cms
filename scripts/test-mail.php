<?php

/**
 * @file
 * Sends one test email, to check the email setup in settings.php (Microsoft
 * Graph, or Gmail SMTP as fallback).
 *
 *   drush php:script scripts/test-mail.php                 # to the notify inbox
 *   drush php:script scripts/test-mail.php -- you@example.org
 *
 * Prints which transport and account are in use, then whether it accepted
 * the message. On failure the reason (Graph: e.g. a 403 when the app may not
 * send as that mailbox; Gmail: "Username and Password not
 * accepted" = wrong app password) is in the recent log messages, shown below.
 */

use Drupal\Core\Site\Settings;
use Drupal\via_api\Mail\GraphTransport;
use Drupal\via_api\ViaMail;

$to = $extra[0] ?? ViaMail::notifyAddress();
$mail = \Drupal::config('system.mail');

echo 'Plugin:  ' . $mail->get('interface.default') . "\n";
// The transport is decided by settings.php, not by the plugin id — see
// settings.via-mail.php and ViaHtmlMailer::getMailer().
$dsn = $mail->get('mailer_dsn');
if ($sender = GraphTransport::senderAddress()) {
  echo "Via:     Microsoft Graph, sending as $sender\n";
}
elseif (($dsn['scheme'] ?? '') === 'smtp') {
  echo "Via:     SMTP {$dsn['host']}:{$dsn['port']} as {$dsn['user']}\n";
}
else {
  echo "! No Microsoft Graph or Gmail credentials in settings.php — using " . ($dsn['scheme'] ?? 'sendmail') . ".\n";
}
echo 'Notify:  ' . ViaMail::notifyAddress() . (Settings::get('via_notify_email') ? '' : " (the site email address)") . "\n";
echo "Sending test email to $to ...\n";

if (ViaMail::send('test', $to)) {
  echo "OK — accepted for delivery. Check the inbox (and spam) for \"VIA Foundation website — test email\".\n";
}
else {
  echo "FAILED. Recent mail errors:\n";
  $rows = \Drupal::database()->select('watchdog', 'w')
    ->fields('w', ['type', 'message', 'variables'])
    ->condition('type', ['mail', 'via_api', 'php'], 'IN')
    ->orderBy('wid', 'DESC')->range(0, 3)->execute();
  foreach ($rows as $row) {
    $vars = @unserialize($row->variables, ['allowed_classes' => FALSE]) ?: [];
    echo '  - ' . strip_tags(strtr($row->message, array_map('strval', array_filter($vars, 'is_scalar')))) . "\n";
  }
}
