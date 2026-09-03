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
 * Receives a job application from a posting's Apply form on /careers/{slug}.
 *
 * multipart/form-data rather than JSON (contrast ContactController) because a
 * résumé travels with it. Submissions land in the job_application webform, so
 * staff review and follow up from
 * /admin/structure/webform/manage/job_application/results/submissions exactly
 * as they would any other webform.
 */
class JobApplicationController extends ControllerBase {

  use WebformFileUploadTrait;

  protected const LIMIT = 5;
  protected const WINDOW = 3600;
  protected const UPLOAD_LOCATION = 'private://job-applications';
  protected const ALLOWED_EXTENSIONS = ['pdf', 'doc', 'docx'];
  protected const MAX_BYTES = 10 * 1024 * 1024;

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

    if (!$this->flood->isAllowed('via_api.job_application', self::LIMIT, self::WINDOW)) {
      return new JsonResponse(['error' => 'Too many submissions. Please try again later.'], 429);
    }

    $jobSlug = trim((string) $request->request->get('job_slug', ''));
    $jobTitle = trim((string) $request->request->get('job_title', ''));
    $name = trim((string) $request->request->get('name', ''));
    $email = trim((string) $request->request->get('email', ''));
    $phone = trim((string) $request->request->get('phone', ''));
    $portfolioUrl = trim((string) $request->request->get('portfolio_url', ''));
    $coverMessage = trim((string) $request->request->get('cover_message', ''));

    $errors = [];
    if ($jobSlug === '') {
      $errors['job_slug'] = 'Missing job posting.';
    }
    else {
      $posting = $this->entityTypeManager()->getStorage('node')->loadByProperties([
        'type' => 'job_posting',
        'field_slug' => $jobSlug,
      ]);
      if (!$posting) {
        $errors['job_slug'] = 'This posting no longer exists.';
      }
      elseif (reset($posting)->get('field_job_status')->value !== 'Open') {
        $errors['job_slug'] = 'This posting is no longer accepting applications.';
      }
    }
    if ($name === '') {
      $errors['name'] = 'Name is required.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $errors['email'] = 'A valid email address is required.';
    }
    if ($coverMessage === '') {
      $errors['cover_message'] = 'Tell us why you\'re a good fit.';
    }

    $resume = $request->files->get('resume');
    $fileId = NULL;
    if (!$resume) {
      $errors['resume'] = 'A résumé is required.';
    }
    else {
      [$fileId, $fileError] = $this->saveWebformUpload($resume, self::UPLOAD_LOCATION, self::ALLOWED_EXTENSIONS, self::MAX_BYTES);
      if ($fileError) {
        $errors['resume'] = $fileError;
      }
    }

    if ($errors) {
      return new JsonResponse(['error' => 'Validation failed.', 'fields' => $errors], 422);
    }

    $submission = WebformSubmission::create([
      'webform_id' => 'job_application',
      'data' => [
        'job_title' => $jobTitle,
        'job_slug' => $jobSlug,
        'name' => $name,
        'email' => $email,
        'phone' => $phone,
        'portfolio_url' => $portfolioUrl,
        'cover_message' => $coverMessage,
        'resume' => $fileId,
      ],
    ]);
    $submission->save();
    $this->registerWebformFileUsage($fileId, (int) $submission->id());

    $this->flood->register('via_api.job_application', self::WINDOW);
    $this->getLogger('via_api')->info('Job application received from @email for @job.', ['@email' => $email, '@job' => $jobSlug]);

    return new JsonResponse(['ok' => TRUE]);
  }

}
