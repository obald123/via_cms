<?php

/**
 * @file
 * Sends one test email, to check the Gmail setup in settings.php.
 *
 *   drush php:script scripts/test-mail.php                 # to the notify inbox
 *   drush php:script scripts/test-mail.php -- you@example.org
 *
 * Prints which mailer and account are in use, then whether Gmail accepted
 * the message. On failure the reason (usually "Username and Password not
 * accepted" = wrong app password) is in the recent log messages, shown below.
 */

use Drupal\Core\Site\Settings;
use Drupal\via_api\ViaMail;

$to = $extra[0] ?? ViaMail::notifyAddress();
$mail = \Drupal::config('system.mail');

echo 'Mailer:  ' . $mail->get('interface.default') . "\n";
if ($mail->get('interface.default') === 'symfony_mailer') {
  $dsn = $mail->get('mailer_dsn');
  echo "Server:  {$dsn['host']}:{$dsn['port']} as {$dsn['user']}\n";
}
else {
  echo "! No Gmail credentials in settings.php — using Drupal's default mailer.\n";
}
echo 'Notify:  ' . ViaMail::notifyAddress() . (Settings::get('via_notify_email') ? '' : " (the site email address)") . "\n";
echo "Sending test email to $to ...\n";

if (ViaMail::send('test', $to)) {
  echo "OK — Gmail accepted it. Check the inbox (and spam) for \"VIA Foundation website — test email\".\n";
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
