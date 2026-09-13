<?php

namespace Drupal\via_api;

use Drupal\Core\Site\Settings;

/**
 * Sends the site's transactional emails (see via_api_mail() for the texts).
 *
 * Delivery is configured entirely in settings.php — Gmail SMTP with an app
 * password, see the "Outgoing email" block there and CLAUDE.md. Nothing here
 * knows about Gmail: with no credentials set, Drupal's default mailer is used.
 *
 * Sending never throws. A form submission is always stored first, so an email
 * that fails (wrong app password, Gmail down) is logged and the visitor still
 * gets a success response — the enquiry is safe in Drupal either way.
 */
final class ViaMail {

  /**
   * Where VIA's own notifications go: $settings['via_notify_email'], falling
   * back to the site email (which settings.php sets to the Gmail account).
   */
  public static function notifyAddress(): string {
    return (string) (Settings::get('via_notify_email') ?: \Drupal::config('system.site')->get('mail'));
  }

  /** Returns TRUE when the mail was handed to the transport successfully. */
  public static function send(string $key, string $to, array $params = [], ?string $replyTo = NULL): bool {
    if ($to === '') {
      return FALSE;
    }
    $result = \Drupal::service('plugin.manager.mail')->mail(
      'via_api', $key, $to, 'en', $params, $replyTo, TRUE
    );
    if (empty($result['result'])) {
      \Drupal::logger('via_api')->error('Could not send "@key" email to @to — check the Gmail settings in settings.php.', ['@key' => $key, '@to' => $to]);
      return FALSE;
    }
    return TRUE;
  }

}
