<?php

namespace Drupal\via_api\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Serves a News item's attached document as a download.
 *
 * The file itself is public and has a plain URL, but that URL lives on the CMS
 * host while the site lives on another, and browsers ignore the `download`
 * attribute across origins — a PDF link would just open in a tab. Sending
 * Content-Disposition: attachment from here is what makes "Download" download.
 *
 * Route access is `node.view`, so an unpublished item's document 404s/403s the
 * same way the item itself would.
 */
class DocumentController extends ControllerBase {

  public function download(NodeInterface $node): BinaryFileResponse {
    if ($node->bundle() !== 'news' || !$node->hasField('field_document') || $node->get('field_document')->isEmpty()) {
      throw new NotFoundHttpException();
    }

    /** @var \Drupal\file\FileInterface|null $file */
    $file = $node->get('field_document')->entity;
    $path = $file ? \Drupal::service('file_system')->realpath($file->getFileUri()) : FALSE;
    if (!$path || !is_file($path)) {
      throw new NotFoundHttpException();
    }

    $response = new BinaryFileResponse($path);
    // Symfony rejects a non-ASCII filename without an ASCII fallback, and an
    // editor can upload "Rapport_annuel_2025_é.pdf" as easily as anything.
    $name = $file->getFilename();
    $ascii = preg_replace('/[^\x20-\x7E]/', '_', $name) ?: 'document';
    $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $name, str_replace(['/', '\\', '%'], '_', $ascii));
    $response->headers->set('Content-Type', $file->getMimeType() ?: 'application/octet-stream');
    return $response;
  }

}
