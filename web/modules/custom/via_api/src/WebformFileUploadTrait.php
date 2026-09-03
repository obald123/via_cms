<?php

namespace Drupal\via_api;

use Drupal\Core\File\FileSystemInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Saves an uploaded file the way a Webform managed_file element would, for
 * controllers that accept multipart/form-data directly rather than going
 * through Drupal's own rendered form.
 *
 * Every file this saves is: written under private:// (see settings.php —
 * nothing under it is reachable by a direct URL), marked permanent (a fresh
 * file defaults to temporary and is deleted by cron within hours), and given
 * a 'webform'/'webform_submission' usage record — the same bookkeeping the
 * Webform module's own file element performs, so a file this trait creates
 * looks, downloads, and is access-checked exactly like one uploaded through
 * a normally-rendered webform.
 */
trait WebformFileUploadTrait {

  /**
   * @return array{0: int|null, 1: string|null}
   *   [file id, error message] — exactly one of the two is set.
   */
  protected function saveWebformUpload(UploadedFile $upload, string $uploadLocation, array $allowedExtensions, int $maxBytes): array {
    if (!$upload->isValid()) {
      return [NULL, 'Upload failed: ' . $upload->getErrorMessage()];
    }
    if ($upload->getSize() > $maxBytes) {
      return [NULL, sprintf('"%s" is too large — the limit is %d MB.', $upload->getClientOriginalName(), intdiv($maxBytes, 1024 * 1024))];
    }
    $ext = strtolower(pathinfo($upload->getClientOriginalName(), PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExtensions, TRUE)) {
      return [NULL, sprintf('"%s" is not an accepted file type. Accepted: %s.', $upload->getClientOriginalName(), implode(', ', $allowedExtensions))];
    }

    /** @var \Drupal\Core\File\FileSystemInterface $fileSystem */
    $fileSystem = \Drupal::service('file_system');
    $fileSystem->prepareDirectory($uploadLocation, FileSystemInterface::CREATE_DIRECTORY);

    // Original names are attacker-controlled input, so they are sanitised
    // rather than trusted verbatim as a filesystem path segment.
    $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $upload->getClientOriginalName());
    $destination = $uploadLocation . '/' . ($safeName ?: ('upload.' . $ext));

    /** @var \Drupal\file\FileRepositoryInterface $fileRepository */
    $fileRepository = \Drupal::service('file.repository');
    $data = file_get_contents($upload->getRealPath());
    if ($data === FALSE) {
      return [NULL, 'Could not read the uploaded file.'];
    }
    $file = $fileRepository->writeData($data, $destination);
    $file->setPermanent();
    $file->save();

    return [(int) $file->id(), NULL];
  }

  /**
   * Registers the usage the Webform module itself expects on a file element
   * value — without this a file is "unused" and cron's file garbage
   * collector deletes it, typically within six hours.
   */
  protected function registerWebformFileUsage(int $fileId, int $submissionId): void {
    $file = \Drupal\file\Entity\File::load($fileId);
    if ($file) {
      \Drupal::service('file.usage')->add($file, 'webform', 'webform_submission', (string) $submissionId);
    }
  }

}
