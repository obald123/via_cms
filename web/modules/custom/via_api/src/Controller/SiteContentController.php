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

      'countries' => $this->map('country', fn(NodeInterface $n) => [
        'country' => $n->label(),
        'lon' => (float) $this->val($n, 'field_lon'),
        'lat' => (float) $this->val($n, 'field_lat'),
        'projects' => (int) $this->val($n, 'field_projects'),
        'trees' => (string) $this->val($n, 'field_trees'),
        'ha' => (string) $this->val($n, 'field_hectares'),
        'hectaresK' => (float) $this->val($n, 'field_hectares_k'),
        'showInChart' => (bool) $this->val($n, 'field_show_in_chart'),
      ]),

      'projects' => $this->map('project', fn(NodeInterface $n) => [
        'slug' => (string) $this->val($n, 'field_slug'),
        'name' => $n->label(),
        'image' => $this->imageUrl($n, 'field_image'),
        'country' => $this->termName($n, 'field_country_ref'),
        'category' => $this->termName($n, 'field_category'),
        'funding' => (string) $this->val($n, 'field_funding'),
        'funder' => (string) $this->val($n, 'field_funder'),
        'status' => (string) $this->val($n, 'field_status'),
        'communities' => (int) $this->val($n, 'field_communities'),
        'trees' => (string) $this->val($n, 'field_trees'),
        'hectares' => (string) $this->val($n, 'field_hectares'),
        'progress' => (int) $this->val($n, 'field_progress'),
        'result' => (string) $this->val($n, 'field_result'),
        'body' => $this->multi($n, 'field_body'),
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

      'fundingAllocation' => $this->map('funding_allocation', fn(NodeInterface $n) => [
        'name' => $n->label(),
        'value' => (int) $this->val($n, 'field_share'),
        'color' => (string) $this->val($n, 'field_color'),
        'weight' => (int) $this->val($n, 'field_weight'),
      ]),

      'yearlyProgress' => $this->map('yearly_progress', fn(NodeInterface $n) => [
        'year' => $n->label(),
        'hectares' => (float) $this->val($n, 'field_hectares_k'),
        'trees' => (float) $this->val($n, 'field_trees_m'),
      ]),

      'testimonials' => $this->map('testimonial', fn(NodeInterface $n) => [
        'initials' => (string) $this->val($n, 'field_initials'),
        'name' => $n->label(),
        'role' => (string) $this->val($n, 'field_role'),
        'quote' => (string) $this->val($n, 'field_quote'),
        'weight' => (int) $this->val($n, 'field_weight'),
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

  /** Absolute URL, because the frontend is served from a different origin. */
  protected function imageUrl(NodeInterface $node, string $field): string {
    if (!$node->hasField($field) || $node->get($field)->isEmpty()) {
      return '';
    }
    $file = $node->get($field)->entity;
    return $file ? $this->fileUrlGenerator->generateAbsoluteString($file->getFileUri()) : '';
  }

}
