<?php

namespace Drupal\via_api\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Flood\FloodInterface;
use Drupal\webform\Entity\WebformSubmission;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Receives the Partner With Us form from the React frontend.
 *
 * Submissions are stored as Webform submissions so they show up under
 * /admin/structure/webform/manage/partner_enquiry/results/submissions and can
 * have email handlers attached in the UI without touching this code.
 */
class ContactController extends ControllerBase {

  /** Max submissions accepted from one IP per hour. */
  protected const LIMIT = 5;
  protected const WINDOW = 3600;

  public function __construct(protected FloodInterface $flood) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('flood'));
  }

  public function post(Request $request): JsonResponse {
    $data = json_decode($request->getContent(), TRUE);
    if (!is_array($data)) {
      return new JsonResponse(['error' => 'Malformed request body.'], 400);
    }

    // Honeypot: a field no human sees, so anything in it is a bot. Answer 200
    // so the bot cannot tell it was rejected.
    if (!empty($data['website'])) {
      return new JsonResponse(['ok' => TRUE]);
    }

    if (!$this->flood->isAllowed('via_api.contact', self::LIMIT, self::WINDOW)) {
      return new JsonResponse(['error' => 'Too many submissions. Please try again later.'], 429);
    }

    $name = trim((string) ($data['name'] ?? ''));
    $email = trim((string) ($data['email'] ?? ''));
    $organization = trim((string) ($data['organization'] ?? ''));
    $message = trim((string) ($data['message'] ?? ''));

    $errors = [];
    if ($name === '') {
      $errors['name'] = 'Name is required.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $errors['email'] = 'A valid email address is required.';
    }
    if ($message === '') {
      $errors['message'] = 'Message is required.';
    }
    if ($errors) {
      return new JsonResponse(['error' => 'Validation failed.', 'fields' => $errors], 422);
    }

    WebformSubmission::create([
      'webform_id' => 'partner_enquiry',
      'data' => [
        'name' => $name,
        'email' => $email,
        'organization' => $organization,
        'message' => $message,
      ],
    ])->save();

    $this->flood->register('via_api.contact', self::WINDOW);
    $this->getLogger('via_api')->info('Partner enquiry received from @email.', ['@email' => $email]);

    return new JsonResponse(['ok' => TRUE]);
  }

}
