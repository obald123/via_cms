<?php

namespace Drupal\via_api\Controller;

use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\File\FileUrlGeneratorInterface;
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
        'slug' => (string) $this->val($n, 'field_slug'),
        'name' => $n->label(),
        'country' => $this->termName($n, 'field_country_ref'),
        'cohort' => (string) $this->val($n, 'field_cohort'),
        'orgType' => (string) $this->val($n, 'field_org_type'),
        'trees' => (string) $this->val($n, 'field_trees'),
        'hectares' => (string) $this->val($n, 'field_hectares'),
        'jobs' => (string) $this->val($n, 'field_jobs'),
        'website' => (string) $this->val($n, 'field_website'),
        'excerpt' => (string) $this->val($n, 'field_excerpt'),
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
      ]),

      'news' => $this->map('news', fn(NodeInterface $n) => [
        'slug' => (string) $this->val($n, 'field_slug'),
        'title' => $n->label(),
        'image' => $this->imageUrl($n, 'field_image'),
        'category' => $this->termName($n, 'field_category'),
        'date' => (string) $this->val($n, 'field_date_label'),
        'body' => $this->multi($n, 'field_body'),
      ]),

      'team' => $this->map('team_member', fn(NodeInterface $n) => [
        'name' => $n->label(),
        'role' => (string) $this->val($n, 'field_role'),
        'location' => (string) $this->val($n, 'field_location'),
        'featured' => (bool) $this->val($n, 'field_featured'),
        'bio' => (string) $this->val($n, 'field_bio'),
        'image' => $this->imageUrl($n, 'field_image'),
        'weight' => (int) $this->val($n, 'field_weight'),
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
  protected function map(string $bundle, callable $mapper): array {
    $storage = $this->entityTypeManager()->getStorage('node');
    $ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', $bundle)
      ->condition('status', NodeInterface::PUBLISHED)
      ->sort('field_weight')
      ->sort('nid')
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

  /** Absolute URL, because the frontend is served from a different origin. */
  protected function imageUrl(NodeInterface $node, string $field): string {
    if (!$node->hasField($field) || $node->get($field)->isEmpty()) {
      return '';
    }
    $file = $node->get($field)->entity;
    return $file ? $this->fileUrlGenerator->generateAbsoluteString($file->getFileUri()) : '';
  }

}
