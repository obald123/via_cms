<?php

namespace Drupal\via_api\Controller;

use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Serves the whole site's editorial content as a single JSON document.
 *
 * The React frontend fetches this once on boot rather than making a dozen
 * JSON:API requests, so every page has its data before the first render and
 * there is only one loading state to design around. The response is cached by
 * Drupal and tagged with node_list, so saving any node invalidates it
 * immediately — editors see their change on the next reload.
 */
class SiteContentController extends ControllerBase {

  public function __construct(protected FileUrlGeneratorInterface $fileUrlGenerator) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('file_url_generator'));
  }

  public function get(): CacheableJsonResponse {
    $payload = [
      'heroStats' => $this->map('hero_stat', fn(NodeInterface $n) => [
        'label' => $n->label(),
        'end' => (int) $this->val($n, 'field_end'),
        'suffix' => (string) $this->val($n, 'field_suffix'),
        'divisor' => max(1, (int) $this->val($n, 'field_divisor', 1)),
        'decimals' => (int) $this->val($n, 'field_decimals'),
      ]),

      'services' => $this->map('service', fn(NodeInterface $n) => [
        'slug' => (string) $this->val($n, 'field_slug'),
        'title' => $n->label(),
        'icon' => (string) $this->val($n, 'field_icon'),
        'accent' => (string) $this->val($n, 'field_accent'),
        'desc' => (string) $this->val($n, 'field_desc'),
        'body' => $this->multi($n, 'field_body'),
      ]),

      'partners' => $this->map('partner', fn(NodeInterface $n) => [
        'name' => $n->label(),
        'weight' => (int) $this->val($n, 'field_weight'),
      ]),

      'impactCards' => $this->map('impact_card', fn(NodeInterface $n) => [
        'icon' => (string) $this->val($n, 'field_icon'),
        'value' => (string) $this->val($n, 'field_value'),
        'label' => $n->label(),
        'sub' => (string) $this->val($n, 'field_sub'),
        'weight' => (int) $this->val($n, 'field_weight'),
      ]),

      // Coordinates only — the figures shown per country are derived from the
      // project list on the frontend, so there is one source of truth.
      'countries' => $this->map('country', fn(NodeInterface $n) => [
        'country' => $n->label(),
        'lon' => (float) $this->val($n, 'field_lon'),
        'lat' => (float) $this->val($n, 'field_lat'),
      ]),

      // Editable section headings, keyed by slug — see field_slug on this
      // bundle for which page/section each row is for.
      'pageHeroes' => $this->map('page_hero', fn(NodeInterface $n) => [
        'slug' => (string) $this->val($n, 'field_slug'),
        'eyebrow' => (string) $this->val($n, 'field_eyebrow'),
        'title' => $n->label(),
        'subtitle' => (string) $this->val($n, 'field_subtitle'),
      ]),

      // Gallery photos and videos. Files live in Drupal, so src/poster are
      // absolute URLs here — the frontend passes them through unchanged.
      'gallery' => $this->map('gallery_item', fn(NodeInterface $n) => [
        'slug' => (string) $this->val($n, 'field_slug'),
        'type' => (string) $this->val($n, 'field_media_type', 'photo'),
        'src' => $this->fileUrl($n, 'field_media'),
        'poster' => $this->imageUrl($n, 'field_poster'),
        'title' => $n->label(),
        'caption' => (string) $this->val($n, 'field_caption'),
        'featured' => (bool) $this->val($n, 'field_featured'),
      ]),

      // A TerraFund champion organisation. Trees, hectares and jobs are the
      // commitments made for the project, not results delivered to date.
      'projects' => $this->map('project', fn(NodeInterface $n) => [
        // A project added by hand with no slug still gets a working page.
        'slug' => (string) ($this->val($n, 'field_slug') ?: $this->slugify($n->label() . '-' . $n->id())),
        'name' => $n->label(),
        'country' => $this->termName($n, 'field_country_ref'),
        'cohort' => (string) $this->val($n, 'field_cohort'),
        // The dropdown; the old free-text field only as a fallback.
        'orgType' => (string) ($this->val($n, 'field_organisation_type') ?: $this->val($n, 'field_org_type')),
        'image' => $this->imageUrl($n, 'field_image'),
        'trees' => (string) $this->val($n, 'field_trees'),
        'hectares' => (string) $this->val($n, 'field_hectares'),
        'jobs' => (string) $this->val($n, 'field_jobs'),
        'website' => (string) $this->val($n, 'field_website'),
        'excerpt' => (string) $this->val($n, 'field_excerpt'),
        'treesDone' => (string) $this->val($n, 'field_trees_done'),
        'hectaresDone' => (string) $this->val($n, 'field_hectares_done'),
        'jobsDone' => (string) $this->val($n, 'field_jobs_done'),
      ]),

      'stories' => $this->map('story', fn(NodeInterface $n) => [
        'slug' => (string) $this->val($n, 'field_slug'),
        'title' => $n->label(),
        'image' => $this->imageUrl($n, 'field_image'),
        'category' => $this->termName($n, 'field_category'),
        'date' => (string) $this->val($n, 'field_date_label'),
        'featured' => (bool) $this->val($n, 'field_featured'),
        'excerpt' => (string) $this->val($n, 'field_excerpt'),
        'body' => $this->multi($n, 'field_body'),
        'youtubeId' => (string) $this->val($n, 'field_youtube_id'),
        'duration' => (string) $this->val($n, 'field_duration'),
        'publishedAt' => (string) $this->val($n, 'field_published_at'),
      ]),

      'news' => $this->map('news', fn(NodeInterface $n) => [
        'slug' => (string) $this->val($n, 'field_slug'),
        'title' => $n->label(),
        'image' => $this->imageUrl($n, 'field_image'),
        'category' => $this->termName($n, 'field_category'),
        'date' => (string) $this->val($n, 'field_date_label'),
        'body' => $this->multi($n, 'field_body'),
      ] + $this->document($n)),

      // Every News category that exists in Drupal, in the vocabulary's own
      // order — not just the ones something is tagged with — so a category
      // like Documentation is filterable (and linkable from the footer) the
      // moment it's created, before its first document is uploaded.
      'newsCategories' => $this->termNames('news_category'),

      'team' => $this->map('team_member', fn(NodeInterface $n) => [
        'name' => $n->label(),
        'role' => (string) $this->val($n, 'field_role'),
        'location' => (string) $this->val($n, 'field_location'),
        'featured' => (bool) $this->val($n, 'field_featured'),
        'bio' => (string) $this->val($n, 'field_bio'),
        'image' => $this->imageUrl($n, 'field_image'),
        'weight' => (int) $this->val($n, 'field_weight'),
      ]),

      // Open roles, entered directly in Drupal by staff — never seeded from
      // the frontend's data.ts, same as news/story/team_member. Newest
      // posting first rather than by field_weight: staff adding a vacancy
      // are very unlikely to also go set an ordering weight for it.
      'jobPostings' => $this->map('job_posting', fn(NodeInterface $n) => [
        'slug' => (string) $this->val($n, 'field_slug'),
        'title' => $n->label(),
        'department' => (string) $this->val($n, 'field_department'),
        'location' => (string) $this->val($n, 'field_location'),
        'employmentType' => (string) $this->val($n, 'field_employment_type'),
        'summary' => (string) $this->val($n, 'field_excerpt'),
        'body' => $this->multi($n, 'field_body'),
        'responsibilities' => $this->multi($n, 'field_responsibilities'),
        'requirements' => $this->multi($n, 'field_requirements'),
        'status' => (string) $this->val($n, 'field_job_status', 'Open'),
        'publishedAt' => (string) $this->val($n, 'field_published_at'),
        'closingAt' => (string) $this->val($n, 'field_closing_at'),
      ], 'field_published_at', 'DESC'),

      // The home page's before/after strip, between Trusted Partners and
      // About. Title doubles as alt text — see restoration_photo's build in
      // scripts/build-content-model.php.
      'restorationTimeline' => $this->map('restoration_photo', fn(NodeInterface $n) => [
        'slug' => (string) $this->val($n, 'field_slug'),
        'image' => $this->imageUrl($n, 'field_image'),
        'alt' => $n->label(),
        'year' => (string) $this->val($n, 'field_year'),
      ]),

      // About page. Staff-owned after a one-time seed — see seed-content.php.
      'purpose' => $this->map('purpose_statement', fn(NodeInterface $n) => [
        'kicker' => (string) $this->val($n, 'field_kicker'),
        'title' => $n->label(),
        'body' => (string) $this->val($n, 'field_desc'),
      ]),

      'pillars' => $this->map('pillar', fn(NodeInterface $n) => [
        'title' => $n->label(),
        'desc' => (string) $this->val($n, 'field_desc'),
      ]),

      'donors' => $this->map('donor', fn(NodeInterface $n) => [
        'name' => $n->label(),
        'logo' => $this->imageUrl($n, 'field_image'),
        'website' => (string) $this->val($n, 'field_website'),
      ]),
    ];

    $response = new CacheableJsonResponse($payload);
    $response->addCacheableDependency(
      (new CacheableMetadata())->setCacheTags(['node_list', 'taxonomy_term_list', 'file_list'])
    );
    return $response;
  }

  /**
   * Loads every published node of a bundle in editor-defined order and maps it.
   */
  protected function map(string $bundle, callable $mapper, string $sortField = 'field_weight', string $sortDirection = 'ASC'): array {
    $storage = $this->entityTypeManager()->getStorage('node');
    $ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', $bundle)
      ->condition('status', NodeInterface::PUBLISHED)
      ->sort($sortField, $sortDirection)
      ->sort('nid', $sortDirection)
      ->execute();

    return array_values(array_map($mapper, $storage->loadMultiple($ids)));
  }

  protected function val(NodeInterface $node, string $field, $default = NULL) {
    return $node->hasField($field) && !$node->get($field)->isEmpty()
      ? $node->get($field)->value
      : $default;
  }

  /** Multi-value text fields become arrays — body paragraphs, chiefly. */
  protected function multi(NodeInterface $node, string $field): array {
    if (!$node->hasField($field)) {
      return [];
    }
    return array_column($node->get($field)->getValue(), 'value');
  }

  protected function termName(NodeInterface $node, string $field): string {
    if (!$node->hasField($field) || $node->get($field)->isEmpty()) {
      return '';
    }
    $term = $node->get($field)->entity;
    return $term ? $term->label() : '';
  }

  /**
   * Absolute URL for a plain file field (video), as opposed to an image field.
   * Same idea as imageUrl(), but file fields store the entity directly rather
   * than through an image-specific item type.
   */
  protected function fileUrl(NodeInterface $node, string $field): string {
    if (!$node->hasField($field) || $node->get($field)->isEmpty()) {
      return '';
    }
    $file = $node->get($field)->entity;
    return $file ? $this->fileUrlGenerator->generateAbsoluteString($file->getFileUri()) : '';
  }

  /**
   * A News item's attached document, or nothing when it has none. The URL is
   * the download route, not the raw file, so it saves instead of opening in a
   * tab — see DocumentController.
   */
  protected function document(NodeInterface $node): array {
    if (!$node->hasField('field_document') || $node->get('field_document')->isEmpty()) {
      return [];
    }
    /** @var \Drupal\file\FileInterface|null $file */
    $file = $node->get('field_document')->entity;
    if (!$file) {
      return [];
    }
    return [
      // toString(TRUE) collects the URL's cache metadata instead of letting it
      // leak into this cacheable response, which Drupal treats as an error.
      'documentUrl' => Url::fromRoute('via_api.document_download', ['node' => $node->id()], ['absolute' => TRUE])
        ->toString(TRUE)->getGeneratedUrl(),
      'documentName' => $file->getFilename(),
      'documentSize' => (int) $file->getSize(),
    ];
  }

  /** "Forest of Hope (FHA)" → "forest-of-hope-fha". */
  protected function slugify(string $text): string {
    $ascii = \Drupal::transliteration()->transliterate($text, 'en');
    return trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($ascii)), '-');
  }

  /** Term names of a vocabulary, in its admin-defined order. */
  protected function termNames(string $vid): array {
    $storage = $this->entityTypeManager()->getStorage('taxonomy_term');
    $ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('vid', $vid)
      ->sort('weight')
      ->sort('name')
      ->execute();
    return array_values(array_map(fn($t) => $t->label(), $storage->loadMultiple($ids)));
  }

  /** Absolute URL, because the frontend is served from a different origin. */
  protected function imageUrl(NodeInterface $node, string $field): string {
    if (!$node->hasField($field) || $node->get($field)->isEmpty()) {
      return '';
    }
    $file = $node->get($field)->entity;
    return $file ? $this->fileUrlGenerator->generateAbsoluteString($file->getFileUri()) : '';
  }

}
