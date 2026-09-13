<?php

namespace Drupal\via_api;

use Drupal\Core\Site\Settings;
use Drupal\Core\Url;
use Drupal\via_api\Email\ViaEmailTemplate;

/**
 * Sends the site's transactional emails: builds the content spec each one
 * renders from (see spec() below) and hands delivery to Drupal's mail system.
 *
 * Delivery is configured in settings.php — Gmail SMTP with an app password,
 * see the "Outgoing email" block there and CLAUDE.md. Nothing here knows
 * about Gmail directly: with no credentials set, Symfony Mailer's default
 * ("sendmail") transport is used instead. Either way, the actual HTML/text
 * rendering happens in \Drupal\via_api\Plugin\Mail\ViaHtmlMailer, which is
 * registered as the default mail plugin by build-content-model.php.
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

  /**
   * The React frontend's own base URL — a different host from this Drupal
   * install, so it can't be built from the current request. Defaults to the
   * documented production address (see CLAUDE.md); override locally with
   * $settings['via_frontend_url'] (e.g. 'http://localhost:5173') to make
   * email links point at a dev server while testing.
   */
  public static function frontendUrl(string $path = '/'): string {
    $base = rtrim((string) (Settings::get('via_frontend_url') ?: 'https://viafoundation.dtecsoftwaresolutions.com'), '/');
    return $base . $path;
  }

  /** Absolute filesystem path to the logo embedded in every email. */
  public static function logoPath(): string {
    return \Drupal::service('extension.list.module')->getPath('via_api') . '/images/via-logo-white.png';
  }

  /** Absolute URL to a webform's submissions list, for a "view in Drupal" link in staff-facing emails. */
  private static function webformResultsUrl(string $webformId): string {
    return Url::fromRoute('entity.webform.results_submissions', ['webform' => $webformId], ['absolute' => TRUE])->toString();
  }

  /**
   * Builds the content spec ViaEmailTemplate renders — see its render() doc
   * comment for the shape. Returns NULL for a key this module doesn't own
   * (ViaHtmlMailer then falls back to plain text), so this is also the single
   * place that lists every email via_api can send.
   *
   * Every value that came from a visitor (name, organisation, message,
   * subscriber address) is escaped here, once, rather than trusting each
   * caller to remember — nothing downstream re-escapes it.
   */
  public static function spec(string $key, array $params): ?array {
    $esc = ViaEmailTemplate::esc(...);
    $escLines = ViaEmailTemplate::escMultiline(...);

    switch ($key) {
      // To VIA: a Partner With Us message. Reply-To is the sender, so
      // replying from the inbox answers them directly — see ContactController.
      case 'partner_enquiry_notify':
        $name = (string) ($params['name'] ?? '');
        $email = (string) ($params['email'] ?? '');
        $org = (string) ($params['organization'] ?? '');
        return [
          'subject' => "New Partner With Us message from $name",
          'preheader' => "New message from $name via the Partner With Us page.",
          'heading' => 'New message from the Partner With Us page',
          'intro' => [$esc($name) . ' just sent a message through the Partner With Us page on the website.'],
          'details' => array_values(array_filter([
            ['Name', $esc($name)],
            ['Email', $esc($email)],
            $org !== '' ? ['Organisation', $esc($org)] : NULL,
          ])),
          'quote' => ['label' => 'Message', 'html' => $escLines((string) ($params['message'] ?? ''))],
          'primaryCta' => $email !== '' ? ['label' => 'Reply to ' . $esc(explode(' ', trim($name))[0]), 'url' => 'mailto:' . $esc($email)] : NULL,
          'secondaryCta' => ['label' => 'View all messages in Drupal', 'url' => self::webformResultsUrl('partner_enquiry')],
        ];

      // To the sender: confirmation that it arrived.
      case 'partner_enquiry_ack':
        $name = (string) ($params['name'] ?? '');
        $firstName = trim(explode(' ', trim($name))[0] ?? '');
        return [
          'subject' => "We've received your message — VIA Foundation",
          'preheader' => "Thanks for contacting VIA Foundation — we'll be in touch soon.",
          'heading' => 'Thanks for reaching out' . ($firstName !== '' ? ', ' . $esc($firstName) : ''),
          'intro' => [
            'Thank you for contacting VIA Foundation. We\'ve received your message and a member of our team will get back to you soon.',
          ],
          'quote' => ['label' => 'What you sent', 'html' => $escLines((string) ($params['message'] ?? ''))],
          'primaryCta' => ['label' => 'See What We Do', 'url' => self::frontendUrl('/what-we-do')],
          'secondaryCta' => ['label' => 'Explore our projects', 'url' => self::frontendUrl('/projects')],
        ];

      // To the subscriber.
      case 'newsletter_welcome':
        return [
          'subject' => "You're subscribed to VIA Foundation updates",
          'preheader' => "You're subscribed to updates from VIA Foundation.",
          'heading' => "You're on the list",
          'intro' => [
            "Thank you for subscribing to updates from VIA Foundation. We'll share news on locally led restoration across Africa, our projects, and new publications.",
            "If you didn't sign up for this, you can ignore this email, or reply and we'll remove your address.",
          ],
          'primaryCta' => ['label' => 'Read Our Latest News', 'url' => self::frontendUrl('/news')],
          'secondaryCta' => ['label' => 'Explore our impact', 'url' => self::frontendUrl('/impact')],
          'footerNote' => "You're receiving this because you subscribed on our website.",
        ];

      // To VIA.
      case 'newsletter_notify':
        $email = (string) ($params['email'] ?? '');
        return [
          'subject' => "New newsletter subscriber: $email",
          'preheader' => "$email subscribed to updates from the website footer.",
          'heading' => 'New newsletter subscriber',
          'intro' => [$esc($email) . ' subscribed to updates from the website footer.'],
          'primaryCta' => ['label' => 'View subscriber list in Drupal', 'url' => self::webformResultsUrl('newsletter_subscription')],
        ];

      // scripts/test-mail.php.
      case 'test':
        return [
          'subject' => 'VIA Foundation website — test email',
          'preheader' => 'A test email from the VIA Foundation website.',
          'heading' => 'This is a test email',
          'intro' => [
            'This is a test email from the VIA Foundation website. If you can read this — with the logo, the colours and this button below — outgoing email through Gmail is set up correctly.',
          ],
          'primaryCta' => ['label' => 'Visit the Website', 'url' => self::frontendUrl('/')],
        ];

      default:
        return NULL;
    }
  }

}
