<?php

namespace Drupal\via_api\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Flood\FloodInterface;
use Drupal\via_api\ViaMail;
use Drupal\webform\Entity\WebformSubmission;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * The footer's "Stay Informed" signup.
 *
 * Subscribers are stored as `newsletter_subscription` webform submissions, so
 * staff can see and export the list at
 * /admin/structure/webform/manage/newsletter_subscription/results/submissions.
 * Each address is stored once: signing up again answers success without a
 * second row or a second welcome email.
 */
class NewsletterController extends ControllerBase {

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

    // Honeypot, same as the contact form.
    if (!empty($data['website'])) {
      return new JsonResponse(['ok' => TRUE]);
    }

    if (!$this->flood->isAllowed('via_api.newsletter', self::LIMIT, self::WINDOW)) {
      return new JsonResponse(['error' => 'Too many attempts. Please try again later.'], 429);
    }

    $email = mb_strtolower(trim((string) ($data['email'] ?? '')));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      return new JsonResponse(['error' => 'Please enter a valid email address.', 'fields' => ['email' => 'Please enter a valid email address.']], 422);
    }
    $this->flood->register('via_api.newsletter', self::WINDOW);

    $existing = \Drupal::database()->select('webform_submission_data', 'd')
      ->condition('d.webform_id', 'newsletter_subscription')
      ->condition('d.name', 'email')
      ->condition('d.value', $email)
      ->countQuery()->execute()->fetchField();
    if ($existing) {
      return new JsonResponse(['ok' => TRUE, 'alreadySubscribed' => TRUE]);
    }

    WebformSubmission::create([
      'webform_id' => 'newsletter_subscription',
      'data' => ['email' => $email],
    ])->save();

    ViaMail::send('newsletter_welcome', $email);
    ViaMail::send('newsletter_notify', ViaMail::notifyAddress(), ['email' => $email]);

    return new JsonResponse(['ok' => TRUE]);
  }

}
