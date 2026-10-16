<?php

namespace Drupal\via_api\Mail;

use Drupal\Core\Site\Settings;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;

/**
 * Symfony Mailer transport that sends through Microsoft Graph's sendMail API
 * instead of SMTP.
 *
 * VIA's Microsoft 365 tenant is reached with an Entra ID app registration
 * ("Website Contact US") using the OAuth2 client-credentials flow — no user
 * sign-in, no SMTP AUTH, no port 25/587, just HTTPS. The app holds the
 * application permission Mail.Send (admin-consented), which lets it send as
 * the one mailbox named in $settings['via_graph_sender'] — unless VIA's IT
 * has scoped it further with an Application Access Policy, in which case a
 * sender outside that policy fails with a 403 "Access is denied".
 *
 * The message goes over as base64 MIME (Graph's text/plain sendMail variant),
 * so the HTML part, the plain-text alternative and the cid:via-logo embed
 * that ViaHtmlMailer built all arrive exactly as built — nothing is
 * re-mapped into Graph's JSON message shape. Graph requires the From header
 * to be the sending mailbox; ViaHtmlMailer sets it from senderAddress().
 *
 * Settings (settings.php, gitignored — the secret is never committed):
 *   via_graph_tenant_id, via_graph_client_id, via_graph_client_secret,
 *   via_graph_sender.
 */
class GraphTransport extends AbstractTransport {

  protected const TOKEN_CACHE_ID = 'via_api:graph_token';

  public function __construct(
    protected ClientInterface $httpClient,
    protected string $tenantId,
    protected string $clientId,
    protected string $clientSecret,
    protected string $sender,
  ) {
    parent::__construct();
  }

  /** Builds the transport from settings.php, or NULL if any Graph value is missing. */
  public static function fromSettings(): ?static {
    $values = [];
    foreach (['tenant_id', 'client_id', 'client_secret', 'sender'] as $key) {
      $values[$key] = trim((string) Settings::get('via_graph_' . $key, ''));
      if ($values[$key] === '') {
        return NULL;
      }
    }
    return new static(\Drupal::httpClient(), $values['tenant_id'], $values['client_id'], $values['client_secret'], $values['sender']);
  }

  /** The mailbox every message is sent as, when Graph is configured. */
  public static function senderAddress(): ?string {
    return static::fromSettings()?->sender;
  }

  protected function doSend(SentMessage $message): void {
    $url = 'https://graph.microsoft.com/v1.0/users/' . rawurlencode($this->sender) . '/sendMail';
    try {
      $this->httpClient->request('POST', $url, [
        'headers' => [
          'Authorization' => 'Bearer ' . $this->accessToken(),
          'Content-Type' => 'text/plain',
        ],
        'body' => base64_encode($message->toString()),
        'timeout' => 30,
      ]);
    }
    catch (RequestException $e) {
      throw new TransportException('Microsoft Graph sendMail failed: ' . self::describe($e), 0, $e);
    }
  }

  /**
   * An app-only Graph token, cached until five minutes before it expires
   * (they last about an hour) so a request that sends two emails — or a
   * busy hour of them — asks Entra ID once.
   */
  protected function accessToken(): string {
    $cache = \Drupal::cache();
    if ($cached = $cache->get(self::TOKEN_CACHE_ID)) {
      return $cached->data;
    }
    try {
      $response = $this->httpClient->request('POST', 'https://login.microsoftonline.com/' . rawurlencode($this->tenantId) . '/oauth2/v2.0/token', [
        'form_params' => [
          'client_id' => $this->clientId,
          'client_secret' => $this->clientSecret,
          'scope' => 'https://graph.microsoft.com/.default',
          'grant_type' => 'client_credentials',
        ],
        'timeout' => 30,
      ]);
    }
    catch (RequestException $e) {
      throw new TransportException('Could not get a Microsoft Graph token: ' . self::describe($e), 0, $e);
    }
    $data = json_decode((string) $response->getBody(), TRUE);
    if (empty($data['access_token'])) {
      throw new TransportException('Microsoft Graph token response had no access_token.');
    }
    $cache->set(self::TOKEN_CACHE_ID, $data['access_token'], time() + max(60, (int) ($data['expires_in'] ?? 3600) - 300));
    return $data['access_token'];
  }

  /** Graph's own error message when it sent one, which says far more than the HTTP status. */
  protected static function describe(RequestException $e): string {
    $body = $e->getResponse() ? json_decode((string) $e->getResponse()->getBody(), TRUE) : NULL;
    $detail = $body['error']['message'] ?? $body['error_description'] ?? NULL;
    $code = $body['error']['code'] ?? $body['error'] ?? NULL;
    if ($detail) {
      return ($e->getResponse() ? $e->getResponse()->getStatusCode() . ' ' : '') . (is_string($code) ? "$code: " : '') . $detail;
    }
    return $e->getMessage();
  }

  public function __toString(): string {
    return 'microsoft-graph://' . $this->sender;
  }

}
