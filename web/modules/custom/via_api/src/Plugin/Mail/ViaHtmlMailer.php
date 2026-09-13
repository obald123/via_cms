<?php

namespace Drupal\via_api\Plugin\Mail;

use Drupal\Core\Mail\Attribute\Mail;
use Drupal\Core\Mail\MailInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Utility\Error;
use Drupal\via_api\Email\ViaEmailTemplate;
use Drupal\via_api\ViaMail;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Sends via_api's own emails (and anything else routed to this interface) as
 * the branded HTML template in ViaEmailTemplate, with a plain-text
 * alternative for clients that don't show HTML.
 *
 * Registered as the default mail plugin by build-content-model.php
 * ('system.mail' interface.default/webform config — a config change, not a
 * settings.php override, so `drush config:set system.mail interface.default
 * test_mail_collector` still works for testing without touching this file).
 *
 * Transport setup (reading system.mail's mailer_dsn, same config Drupal core's
 * own "Symfony mailer" plugin uses) is copied from
 * \Drupal\Core\Mail\Plugin\Mail\SymfonyMailer rather than extending it, since
 * that class's mail() always calls ->text() and never ->html() — there is no
 * way to get HTML out of it via subclassing alone.
 *
 * $message['key'] and $message['params'] (untouched by MailManager) are what
 * ViaMail::spec() needs to rebuild the same content this module's hook_mail()
 * already turned into a subject/body — see via_api_mail(). That keeps one
 * source of truth for the copy while this class owns only the HTML shell.
 */
#[Mail(
  id: 'via_html_mailer',
  label: new TranslatableMarkup('VIA branded HTML mailer'),
)]
class ViaHtmlMailer implements MailInterface, ContainerFactoryPluginInterface {

  protected const MAILBOX_LIST_HEADERS = ['from', 'to', 'reply-to', 'cc', 'bcc'];
  protected const SKIP_HEADERS = ['content-type', 'content-transfer-encoding'];

  protected ?MailerInterface $mailer = NULL;

  public function __construct(protected LoggerInterface $logger) {}

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static($container->get('logger.channel.mail'));
  }

  /** Untouched — MailManager already ran this through via_api_mail() for the plain-text fallback. */
  public function format(array $message) {
    return $message;
  }

  public function mail(array $message) {
    try {
      $spec = ViaMail::spec($message['key'] ?? '', $message['params'] ?? []);
      if (!$spec) {
        // Not one of via_api's own keys (some other module sent through this
        // interface) — fall back to the plain body MailManager already built.
        $body = is_array($message['body']) ? implode("\n\n", $message['body']) : (string) $message['body'];
        $email = (new Email())->text($body);
      }
      else {
        $email = (new Email())
          ->html(ViaEmailTemplate::render($spec))
          ->text(ViaEmailTemplate::renderText($spec))
          ->embedFromPath(ViaMail::logoPath(), 'via-logo', 'image/png');
      }

      $headers = $email->getHeaders();
      foreach ($message['headers'] as $name => $value) {
        if (in_array(strtolower($name), self::SKIP_HEADERS, TRUE)) {
          continue;
        }
        // 'From' is handled separately below: Drupal builds it from the site's
        // configured name, which here reads "VIA Foundation CMS" — the
        // backend's own install name, not the brand. Every VIA email should
        // say "VIA Foundation" regardless of what the Drupal site is called.
        if (strtolower($name) === 'from') {
          continue;
        }
        if (in_array(strtolower($name), self::MAILBOX_LIST_HEADERS, TRUE)) {
          $value = str_getcsv($value, escape: '\\');
        }
        $headers->addHeader($name, $value);
      }

      $fromAddress = !empty($message['headers']['From'])
        ? Address::create($message['headers']['From'])->getAddress()
        : ViaMail::notifyAddress();
      $email->from(new Address($fromAddress, 'VIA Foundation'));

      $email->to($message['to'])->subject($message['subject']);

      $this->getMailer()->send($email);
      return TRUE;
    }
    catch (\Exception $e) {
      Error::logException($this->logger, $e);
      return FALSE;
    }
  }

  protected function getMailer(): MailerInterface {
    if (!isset($this->mailer)) {
      $dsn = \Drupal::config('system.mail')->get('mailer_dsn');
      $dsnObject = new Dsn(...$dsn);
      $factories = Transport::getDefaultFactories(logger: $this->logger);
      $transport = (new Transport($factories))->fromDsnObject($dsnObject);
      $this->mailer = new Mailer($transport);
    }
    return $this->mailer;
  }

}
