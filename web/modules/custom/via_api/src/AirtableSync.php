<?php

namespace Drupal\via_api;

use Drupal\Core\Site\Settings;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\Entity\Term;

/**
 * Pulls project rows from the "VIA Foundation Website Data" Airtable base
 * and upserts them as `project` nodes — the automated replacement for
 * staff typing TerraFund/PPC's reported numbers into Drupal by hand.
 *
 * Airtable is the source of truth for exactly one slice of a project: its
 * name, country, cohort, and the six commitment/delivered figures (trees,
 * hectares, jobs — see applyFields()). Everything a human writes —
 * organisation type, website, photo, description — is staff-owned in
 * Drupal, same as the rest of this content type (see field_trees_done etc.
 * in add-project-fields.php), and this sync never touches those fields once
 * a node exists.
 *
 * Matching: every Airtable row carries a stable `uuid`. A node linked to one
 * (field_airtable_uuid) is always found and updated by that key. A node
 * without the field set yet — the ~115 profiles seeded from data.ts before
 * this integration existed — is matched once, by exact title ==
 * organisationName, and linked; every sync after that uses the uuid. An
 * unmatched row creates a new stub node: organisation type defaults to
 * Non-profit, and website/photo/description are left blank for staff to
 * fill in in Drupal.
 *
 * Triggered by AirtableWebhookController on a verified webhook ping, and by
 * scripts/sync-airtable-projects.php for a manual run.
 */
class AirtableSync {

  /**
   * Airtable's `Project Country` spelling → the name already used by the
   * site's project_country taxonomy and by AfricaMap.tsx's GEO_NAME table.
   * Without this, an exact-match get-or-create term lookup creates a second,
   * differently-spelled country term instead of reusing the one the map and
   * the country charts already key off — which is exactly what happened to
   * DRC the first time this ran: its projects got filed under "Congo,
   * Democratic Republic of the", a label nothing else on the site
   * recognises, so the map silently dropped its marker and the country bar
   * chart split DRC's totals into an orphan bucket. Add any other alias
   * here the moment a source spells a country differently.
   */
  protected const COUNTRY_ALIASES = [
    'Congo, Democratic Republic of the' => 'DRC',
  ];

  public function __construct(
    protected string $token,
    protected string $baseId,
    protected string $tableId,
  ) {}

  /**
   * Builds from settings.php, or NULL if the integration isn't configured.
   *
   *   $settings['via_airtable_token'] = 'patXXXXXXXXXXXXXX...';
   *   $settings['via_airtable_base_id'] = 'appZ0UyownlLxViM9';
   *   $settings['via_airtable_table_id'] = 'tblwIBXl64MRsWRs9';
   */
  public static function fromSettings(): ?self {
    $token = trim((string) Settings::get('via_airtable_token', ''));
    $baseId = trim((string) Settings::get('via_airtable_base_id', ''));
    $tableId = trim((string) Settings::get('via_airtable_table_id', ''));
    if ($token === '' || $baseId === '' || $tableId === '') {
      return NULL;
    }
    return new self($token, $baseId, $tableId);
  }

  /**
   * Pulls every row and upserts the matching project node.
   *
   * @return array{total: int, created: int, updated: int, linked: int}
   */
  public function sync(): array {
    $records = $this->fetchAllRecords();
    $created = 0;
    $updated = 0;
    $linked = 0;

    foreach ($records as $record) {
      $fields = $record['fields'] ?? [];
      $uuid = trim((string) ($fields['uuid'] ?? ''));
      if ($uuid === '') {
        // A row with no uuid can't be matched or re-matched safely — skip it
        // rather than risk creating a duplicate on every future sync.
        continue;
      }

      $orgName = trim((string) ($fields['organisationName'] ?? $fields['name'] ?? ''));
      if ($orgName === '') {
        continue;
      }

      $node = $this->findByUuid($uuid);
      if (!$node) {
        $node = $this->findUnlinkedByTitle($orgName);
        if ($node) {
          $linked++;
        }
      }

      $isNew = !$node;
      $node ??= Node::create(['type' => 'project', 'status' => 1]);

      $this->applyFields($node, $fields, $isNew);
      $node->save();
      $isNew ? $created++ : $updated++;
    }

    return ['total' => count($records), 'created' => $created, 'updated' => $updated, 'linked' => $linked];
  }

  /**
   * Writes the Airtable-owned fields onto a node. Never touches
   * field_organisation_type, field_website, field_image, field_excerpt,
   * field_slug or field_weight once the node already exists.
   */
  protected function applyFields(NodeInterface $node, array $f, bool $isNew): void {
    $orgName = trim((string) ($f['organisationName'] ?? $f['name'] ?? ''));
    if ($orgName !== '') {
      $node->setTitle($orgName);
    }
    $node->set('field_airtable_uuid', trim((string) ($f['uuid'] ?? '')));

    $cohort = trim((string) ($f['Project Cohort'] ?? ''));
    if ($cohort !== '') {
      $node->set('field_cohort', str_replace('TerraFund ', '', $cohort));
    }

    $country = trim((string) ($f['Project Country'] ?? ''));
    if ($country !== '') {
      $country = self::COUNTRY_ALIASES[$country] ?? $country;
      $node->set('field_country_ref', ['target_id' => $this->countryTermId($country)]);
    }

    // Trees Planted + ANR (assisted natural regeneration) are both trees
    // restored — VIA's own seed data (see CLAUDE.md's "seed version 3" note)
    // already sums the two, so the sync matches that.
    $treesGoal = $this->num($f, 'Trees Planted Goal') + $this->num($f, 'ANR Goal');
    $treesDone = $this->num($f, 'treesPlantedToDate') + $this->num($f, 'anrTreesToDate');
    $node->set('field_trees', $this->format($treesGoal));
    $node->set('field_trees_done', $this->format($treesDone));
    $node->set('field_hectares', $this->format($this->num($f, 'Hectares Restored Goal')));
    $node->set('field_hectares_done', $this->format($this->num($f, 'hectaresRestoredToDate')));
    $node->set('field_jobs', $this->format($this->num($f, 'jobsCreatedGoal')));
    // "jobs" in Airtable is the current/delivered count, not the goal —
    // jobsCreatedGoal is the target, confirmed against sample rows.
    $node->set('field_jobs_done', $this->format($this->num($f, 'jobs')));

    if ($isNew && $node->hasField('field_organisation_type') && $node->get('field_organisation_type')->isEmpty()) {
      $node->set('field_organisation_type', 'Non-profit');
    }
  }

  protected function num(array $f, string $key): int {
    return (int) ($f[$key] ?? 0);
  }

  protected function format(int $n): string {
    return number_format($n);
  }

  protected function findByUuid(string $uuid): ?NodeInterface {
    $nodes = \Drupal::entityTypeManager()->getStorage('node')
      ->loadByProperties(['type' => 'project', 'field_airtable_uuid' => $uuid]);
    return $nodes ? reset($nodes) : NULL;
  }

  /** First project node with this title that has no Airtable link yet. */
  protected function findUnlinkedByTitle(string $title): ?NodeInterface {
    $nodes = \Drupal::entityTypeManager()->getStorage('node')
      ->loadByProperties(['type' => 'project', 'title' => $title]);
    foreach ($nodes as $node) {
      if ($node->hasField('field_airtable_uuid') && $node->get('field_airtable_uuid')->isEmpty()) {
        return $node;
      }
    }
    return NULL;
  }

  protected function countryTermId(string $name): int {
    $storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
    $existing = $storage->loadByProperties(['vid' => 'project_country', 'name' => $name]);
    if ($existing) {
      return (int) reset($existing)->id();
    }
    $term = Term::create(['vid' => 'project_country', 'name' => $name]);
    $term->save();
    return (int) $term->id();
  }

  /** @return array<int, array{id: string, fields: array}> */
  protected function fetchAllRecords(): array {
    $client = \Drupal::httpClient();
    $records = [];
    $offset = NULL;
    do {
      $query = ['pageSize' => 100];
      if ($offset) {
        $query['offset'] = $offset;
      }
      $response = $client->request('GET', "https://api.airtable.com/v0/{$this->baseId}/{$this->tableId}", [
        'headers' => ['Authorization' => 'Bearer ' . $this->token],
        'query' => $query,
      ]);
      $data = json_decode((string) $response->getBody(), TRUE) ?: [];
      $records = array_merge($records, $data['records'] ?? []);
      $offset = $data['offset'] ?? NULL;
    } while ($offset);
    return $records;
  }

  /* ── Webhook lifecycle ──────────────────────────────────────────────── */

  /**
   * Creates the Airtable webhook if none is registered yet, or refreshes the
   * existing one (they expire after 7 days of no refresh). Safe to call
   * repeatedly — see scripts/setup-airtable-webhook.php and the cron hook in
   * via_api.module.
   */
  public function createOrRefreshWebhook(string $notificationUrl): array {
    $state = \Drupal::state();
    $webhookId = $state->get('via_airtable.webhook_id');
    $client = \Drupal::httpClient();

    if ($webhookId) {
      try {
        $client->request('POST', "https://api.airtable.com/v0/bases/{$this->baseId}/webhooks/{$webhookId}/refresh", [
          'headers' => ['Authorization' => 'Bearer ' . $this->token],
        ]);
        $state->set('via_airtable.webhook_refreshed', \Drupal::time()->getRequestTime());
        return ['status' => 'refreshed', 'id' => $webhookId];
      }
      catch (\Throwable $e) {
        // Expired past recovery, or deleted from the Airtable side — fall
        // through and register a new one instead of failing the whole run.
        \Drupal::logger('via_api')->warning('Airtable webhook refresh failed, recreating: @msg', ['@msg' => $e->getMessage()]);
      }
    }

    $response = $client->request('POST', "https://api.airtable.com/v0/bases/{$this->baseId}/webhooks", [
      'headers' => ['Authorization' => 'Bearer ' . $this->token, 'Content-Type' => 'application/json'],
      'json' => [
        'notificationUrl' => $notificationUrl,
        'specification' => [
          'options' => [
            'filters' => [
              'dataTypes' => ['tableData'],
              'recordChangeScope' => $this->tableId,
            ],
          ],
        ],
      ],
    ]);
    $data = json_decode((string) $response->getBody(), TRUE) ?: [];
    $state->setMultiple([
      'via_airtable.webhook_id' => $data['id'] ?? NULL,
      'via_airtable.mac_secret' => $data['macSecretBase64'] ?? NULL,
      'via_airtable.webhook_refreshed' => \Drupal::time()->getRequestTime(),
    ]);
    return ['status' => 'created', 'id' => $data['id'] ?? NULL];
  }

  /**
   * Verifies an incoming webhook ping really came from Airtable.
   *
   * Per Airtable's webhook docs: X-Airtable-Content-MAC is
   * "hmac-sha256=" + hex(HMAC-SHA256(rawBody, base64_decode(macSecret))).
   */
  public static function verifySignature(string $rawBody, ?string $signatureHeader): bool {
    $macSecret = \Drupal::state()->get('via_airtable.mac_secret');
    if (!$macSecret || !$signatureHeader) {
      return FALSE;
    }
    $expected = 'hmac-sha256=' . hash_hmac('sha256', $rawBody, base64_decode($macSecret));
    return hash_equals($expected, $signatureHeader);
  }

}
