<?php

namespace Drupal\via_api\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Flood\FloodInterface;
use Drupal\via_api\WebformFileUploadTrait;
use Drupal\webform\Entity\WebformSubmission;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Receives a report from /report-a-concern.
 *
 * Two things make this different from every other form on the site:
 *
 * - The whistleblower_report webform has `form_disable_remote_addr: true`,
 *   which is what actually stops Drupal recording an IP address on the
 *   submission — see WebformSubmission::preCreate()/save() and the comment
 *   above that setting in scripts/build-content-model.php. It applies to
 *   every submission to this form, not only ones marked anonymous, so there
 *   is no per-request branch here that could get that backwards.
 * - When `anonymous` is checked, reporter_name/reporter_email are dropped
 *   server-side even if a client bug sent them — the promise is enforced
 *   here, not trusted to the frontend having honoured it.
 *
 * The flood check below uses the request's IP the same transient way a
 * failed-login lockout does: as a short-lived counter key, never written
 * into the submission it protects. That is a different thing from recording
 * an IP against a report, which this endpoint never does for anyone.
 */
class WhistleblowerController extends ControllerBase {

  use WebformFileUploadTrait;

  protected const LIMIT = 5;
  protected const WINDOW = 3600;
  protected const UPLOAD_LOCATION = 'private://whistleblower-reports';
  protected const ALLOWED_EXTENSIONS = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
  protected const MAX_BYTES = 10 * 1024 * 1024;
  protected const MAX_FILES = 5;

  protected const CONCERN_TYPES = [
    'fraud_financial', 'corruption_bribery', 'conflict_of_interest', 'safeguarding',
    'sexual_harassment', 'discrimination', 'data_privacy', 'environmental_safety', 'other',
  ];

  public function __construct(protected FloodInterface $flood) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('flood'));
  }

  public function post(Request $request): JsonResponse {
    // Honeypot: a field no human sees, so anything in it is a bot. Answer 200
    // so the bot cannot tell it was rejected.
    if (!empty($request->request->get('website'))) {
      return new JsonResponse(['ok' => TRUE]);
    }

    if (!$this->flood->isAllowed('via_api.whistleblower', self::LIMIT, self::WINDOW)) {
      return new JsonResponse(['error' => 'Too many submissions. Please try again later.'], 429);
    }

    $anonymous = in_array($request->request->get('anonymous'), ['1', 'true', 'on'], TRUE);
    $name = $anonymous ? '' : trim((string) $request->request->get('reporter_name', ''));
    $email = $anonymous ? '' : trim((string) $request->request->get('reporter_email', ''));
    $concernTypes = array_values(array_intersect((array) $request->request->all('concern_types'), self::CONCERN_TYPES));
    $relatedTo = trim((string) $request->request->get('related_project_or_office', ''));
    $personInvolved = trim((string) $request->request->get('person_involved', ''));
    $incidentDate = trim((string) $request->request->get('incident_date', ''));
    $description = trim((string) $request->request->get('description', ''));

    $errors = [];
    if (!$anonymous) {
      if ($name === '') {
        $errors['reporter_name'] = 'Name is required unless you report anonymously.';
      }
      if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['reporter_email'] = 'A valid email is required unless you report anonymously.';
      }
    }
    if (!$concernTypes) {
      $errors['concern_types'] = 'Select at least one category.';
    }
    if ($description === '') {
      $errors['description'] = 'Please describe what happened.';
    }

    $uploads = $request->files->all('attachments') ?: [];
    if (count($uploads) > self::MAX_FILES) {
      $errors['attachments'] = sprintf('Up to %d files only.', self::MAX_FILES);
    }

    $fileIds = [];
    if (!$errors) {
      foreach ($uploads as $upload) {
        [$fileId, $fileError] = $this->saveWebformUpload($upload, self::UPLOAD_LOCATION, self::ALLOWED_EXTENSIONS, self::MAX_BYTES);
        if ($fileError) {
          $errors['attachments'] = $fileError;
          break;
        }
        $fileIds[] = $fileId;
      }
    }

    if ($errors) {
      return new JsonResponse(['error' => 'Validation failed.', 'fields' => $errors], 422);
    }

    $submission = WebformSubmission::create([
      'webform_id' => 'whistleblower_report',
      'data' => [
        'anonymous' => $anonymous ? 1 : 0,
        'reporter_name' => $name,
        'reporter_email' => $email,
        // A plain, 0-indexed array — webform_submission_data.delta is an
        // integer column, so the string-keyed array checkboxes normally
        // submit as (via Form API) doesn't fit here; this bypasses Form API
        // entirely and writes storage's own expected shape directly.
        'concern_types' => $concernTypes,
        'related_project_or_office' => $relatedTo,
        'person_involved' => $personInvolved,
        'incident_date' => $incidentDate,
        'description' => $description,
        'attachments' => $fileIds,
      ],
    ]);
    $submission->save();
    foreach ($fileIds as $fileId) {
      $this->registerWebformFileUsage($fileId, (int) $submission->id());
    }

    $this->flood->register('via_api.whistleblower', self::WINDOW);
    // Deliberately no email/name here, identified report or not — the
    // watchdog log is a different, wider-access system than the webform
    // submission it would otherwise be duplicating PII into.
    $this->getLogger('via_api')->info('Whistleblower report #@id received (anonymous: @anon).', [
      '@id' => $submission->id(),
      '@anon' => $anonymous ? 'yes' : 'no',
    ]);

    return new JsonResponse(['ok' => TRUE]);
  }

}
