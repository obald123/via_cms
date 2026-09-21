<?php

namespace Drupal\via_api\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\via_api\AirtableSync;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Receives Airtable's "something changed" ping for the projects base and
 * re-syncs every project node from it.
 *
 * The ping itself carries no record data — just base/webhook ids and a
 * timestamp — so there's nothing to diff; a full re-sync of the ~130 rows is
 * cheap and simpler to reason about than tracking Airtable's payload cursor.
 * See AirtableSync for the field mapping and what stays staff-owned.
 *
 * Not a Drupal permission check: Airtable isn't a logged-in user. Authenticity
 * is the HMAC signature in X-Airtable-Content-MAC instead (verified against
 * the secret handed back when the webhook was created — see
 * AirtableSync::createOrRefreshWebhook()). An unsigned or wrongly-signed
 * request is rejected before anything runs.
 */
class AirtableWebhookController extends ControllerBase {

  public function post(Request $request): JsonResponse {
    $sync = AirtableSync::fromSettings();
    if (!$sync) {
      return new JsonResponse(['error' => 'Airtable sync is not configured.'], 503);
    }

    $signature = $request->headers->get('X-Airtable-Content-MAC');
    if (!AirtableSync::verifySignature($request->getContent(), $signature)) {
      $this->getLogger('via_api')->warning('Rejected an Airtable webhook ping with an invalid signature.');
      return new JsonResponse(['error' => 'Invalid signature.'], 401);
    }

    $result = $sync->sync();
    $this->getLogger('via_api')->info(
      'Airtable project sync (webhook): @total rows, @created created, @updated updated (@linked newly linked to an existing profile).',
      ['@total' => $result['total'], '@created' => $result['created'], '@updated' => $result['updated'], '@linked' => $result['linked']]
    );

    return new JsonResponse(['ok' => TRUE] + $result);
  }

}
