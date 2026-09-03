<?php

/**
 * @file
 * Builds the VIA Foundation content model.
 *
 * Every content type and field below maps 1:1 onto a key that the React
 * frontend used to import from src/app/data.ts. Building this in code rather
 * than by hand in the UI means the model is reproducible: run it once locally,
 * `drush cex`, then `drush cim` on cPanel to get an identical model there.
 *
 * Idempotent — re-running skips anything that already exists.
 *
 *   drush php:script scripts/build-content-model.php
 */

use Drupal\Component\Serialization\Yaml;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\NodeType;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;

/* ── Vocabularies and their seed terms ─────────────────────────────────── */
$vocabularies = [
  'project_country' => ['Project country', ['Zambia', 'Senegal', 'Kenya', 'DRC', 'Ethiopia', 'Tanzania']],
  'project_category' => ['Project category', ['Forest', 'Drylands', 'Watershed', 'Agroforestry', 'Highlands', 'Coastal']],
  'story_category' => ['Story category', ['Community', 'Innovation', 'Finance', 'Youth']],
  'news_category' => ['News category', ['Report', 'Publication', 'News']],
];

/* The lucide-react icons the frontend can render. Shared storage, so this is
   the union of the icons used by services and impact cards. */
$icons = ['DollarSign', 'Shield', 'Globe', 'Layers', 'Leaf', 'Users', 'TrendingUp', 'TreePine'];

$employmentTypes = ['Full-time', 'Part-time', 'Contract', 'Internship', 'Volunteer'];

/* ── Field storages: name => [type, cardinality, extra settings] ────────── */
$storages = [
  'field_slug' => ['string', 1],
  'field_image' => ['image', 1],
  'field_body' => ['string_long', -1],
  'field_category' => ['entity_reference', 1, ['target_type' => 'taxonomy_term']],
  'field_country_ref' => ['entity_reference', 1, ['target_type' => 'taxonomy_term']],
  'field_weight' => ['integer', 1],
  'field_date_label' => ['string', 1],
  'field_featured' => ['boolean', 1],
  'field_excerpt' => ['string_long', 1],
  'field_icon' => ['list_string', 1, ['allowed_values' => array_combine($icons, $icons)]],
  'field_accent' => ['string', 1],
  'field_desc' => ['string_long', 1],
  'field_funding' => ['string', 1],
  'field_funder' => ['string', 1],
  'field_status' => ['list_string', 1, ['allowed_values' => ['Active' => 'Active', 'Completed' => 'Completed']]],
  'field_communities' => ['integer', 1],
  'field_trees' => ['string', 1],
  'field_hectares' => ['string', 1],
  'field_progress' => ['integer', 1],
  'field_result' => ['string', 1],
  'field_role' => ['string', 1],
  'field_location' => ['string', 1],
  'field_bio' => ['string_long', 1],
  'field_initials' => ['string', 1],
  'field_quote' => ['string_long', 1],
  'field_end' => ['integer', 1],
  'field_suffix' => ['string', 1],
  'field_divisor' => ['integer', 1],
  'field_decimals' => ['integer', 1],
  'field_value' => ['string', 1],
  'field_sub' => ['string', 1],
  'field_lat' => ['decimal', 1, ['precision' => 10, 'scale' => 5]],
  'field_lon' => ['decimal', 1, ['precision' => 10, 'scale' => 5]],
  'field_projects' => ['integer', 1],
  'field_hectares_k' => ['decimal', 1, ['precision' => 10, 'scale' => 2]],
  'field_trees_m' => ['decimal', 1, ['precision' => 10, 'scale' => 2]],
  'field_show_in_chart' => ['boolean', 1],
  'field_share' => ['integer', 1],
  'field_color' => ['string', 1],

  // Careers. field_published_at is also created by scripts/add-story-fields.php
  // — declared again here (harmlessly, loadByName() skips it if present) so
  // build-content-model.php alone is enough to stand the job board up.
  'field_department' => ['string', 1],
  'field_employment_type' => ['list_string', 1, ['allowed_values' => array_combine($employmentTypes, $employmentTypes)]],
  'field_responsibilities' => ['string_long', -1],
  'field_requirements' => ['string_long', -1],
  'field_job_status' => ['list_string', 1, ['allowed_values' => ['Open' => 'Open', 'Closed' => 'Closed']]],
  'field_published_at' => ['datetime', 1, ['datetime_type' => 'date']],
  'field_closing_at' => ['datetime', 1, ['datetime_type' => 'date']],

  // Restoration timeline band (home page, between Trusted Partners and About).
  'field_year' => ['string', 1],
];

/* ── Content types: machine => [label, title label, description, fields] ──
   A field entry is either 'field_name' or 'field_name' => [label, settings]. */
$types = [
  'project' => ['Project', 'Name', 'A restoration project VIA Foundation finances.', [
    'field_slug' => ['URL slug'],
    'field_image' => ['Image'],
    'field_country_ref' => ['Country', ['handler_settings' => ['target_bundles' => ['project_country' => 'project_country']]]],
    'field_category' => ['Category', ['handler_settings' => ['target_bundles' => ['project_category' => 'project_category']]]],
    'field_funding' => ['Funding'],
    'field_funder' => ['Funder'],
    'field_status' => ['Status'],
    'field_communities' => ['Communities'],
    'field_trees' => ['Trees'],
    'field_hectares' => ['Hectares'],
    'field_progress' => ['Progress (%)'],
    'field_result' => ['Headline result'],
    'field_body' => ['Body paragraphs'],
    'field_weight' => ['Order'],
  ]],
  'story' => ['Story', 'Title', 'A field story from the VIA Foundation portfolio.', [
    'field_slug' => ['URL slug'],
    'field_image' => ['Image'],
    'field_category' => ['Category', ['handler_settings' => ['target_bundles' => ['story_category' => 'story_category']]]],
    'field_date_label' => ['Date label'],
    'field_featured' => ['Featured'],
    'field_excerpt' => ['Excerpt'],
    'field_body' => ['Body paragraphs'],
    'field_weight' => ['Order'],
  ]],
  'news' => ['News item', 'Title', 'News and publications.', [
    'field_slug' => ['URL slug'],
    'field_image' => ['Image'],
    'field_category' => ['Category', ['handler_settings' => ['target_bundles' => ['news_category' => 'news_category']]]],
    'field_date_label' => ['Date label'],
    'field_body' => ['Body paragraphs'],
    'field_weight' => ['Order'],
  ]],
  'service' =>['Service', 'Title', 'A "What We Do" service offering.', [
    'field_slug' => ['URL slug'],
    'field_icon' => ['Icon'],
    'field_accent' => ['Accent colour (hex)'],
    'field_desc' => ['Short description'],
    'field_body' => ['Body paragraphs'],
    'field_weight' => ['Order'],
  ]],
  'team_member' => ['Team member', 'Name', 'A member of the VIA Foundation team.', [
    'field_role' => ['Role'],
    'field_location' => ['Location'],
    'field_featured' => ['Featured (CEO card)'],
    'field_bio' => ['Bio'],
    'field_image' => ['Photo'],
    'field_weight' => ['Order'],
  ]],
  'partner' => ['Partner', 'Name', 'A partner organisation shown in the logo marquee.', [
    'field_weight' => ['Order'],
  ]],
  'testimonial' => ['Testimonial', 'Person name', 'A partner testimonial quote.', [
    'field_initials' => ['Initials'],
    'field_role' => ['Role'],
    'field_quote' => ['Quote'],
    'field_weight' => ['Order'],
  ]],
  'hero_stat' => ['Hero stat', 'Label', 'A counter in the homepage hero.', [
    'field_end' => ['Target value'],
    'field_suffix' => ['Suffix'],
    'field_divisor' => ['Divisor (display value = target / divisor)'],
    'field_decimals' => ['Decimal places'],
    'field_weight' => ['Order'],
  ]],
  'impact_card' => ['Impact card', 'Label', 'A card in the impact grid.', [
    'field_icon' => ['Icon'],
    'field_value' => ['Value'],
    'field_sub' => ['Sub-label'],
    'field_weight' => ['Order'],
  ]],
  'country' => ['Country', 'Country', 'Feeds both the Africa map markers and the country bar chart.', [
    'field_lat' => ['Latitude'],
    'field_lon' => ['Longitude'],
    'field_projects' => ['Projects'],
    'field_trees' => ['Trees (display, e.g. 3.2M)'],
    'field_hectares' => ['Hectares (display, e.g. 22,000)'],
    'field_hectares_k' => ['Hectares (thousands, for the chart)'],
    'field_show_in_chart' => ['Show in bar chart'],
    'field_weight' => ['Order'],
  ]],
  'funding_allocation' => ['Funding allocation', 'Segment name', 'A slice of the funding allocation pie chart.', [
    'field_share' => ['Share (%)'],
    'field_color' => ['Colour (hex)'],
    'field_weight' => ['Order'],
  ]],
  'yearly_progress' => ['Yearly progress', 'Year', 'A point on the cumulative progress area chart.', [
    'field_hectares_k' => ['Hectares (thousands)'],
    'field_trees_m' => ['Trees (millions)'],
    'field_weight' => ['Order'],
  ]],
  'restoration_photo' => ['Restoration photo', 'Alt text', 'One photo in the before/after strip on the home page, between Trusted Partners and About. The title doubles as the image\'s alt text.', [
    'field_slug' => ['URL slug'],
    'field_image' => ['Image'],
    'field_year' => ['Year label (e.g. 2019 — illustrative, shown even though it is not the photo\'s real capture date)'],
    'field_weight' => ['Order (left to right)'],
  ]],
  'job_posting' => ['Job posting', 'Title', 'An open role, listed on /careers and applied to directly through the site.', [
    'field_slug' => ['URL slug'],
    'field_department' => ['Department'],
    'field_location' => ['Location'],
    'field_employment_type' => ['Employment type'],
    'field_excerpt' => ['Summary (shown on the listing card)'],
    'field_body' => ['Full description'],
    'field_responsibilities' => ['Responsibilities (one per paragraph)'],
    'field_requirements' => ['Requirements (one per paragraph)'],
    'field_job_status' => ['Status'],
    'field_published_at' => ['Posted date'],
    'field_closing_at' => ['Application deadline (leave empty for "open until filled")'],
    'field_weight' => ['Order'],
  ]],
];

/* Widget per field type — without a form display component the field would not
   appear on the node edit form at all. */
$widgets = [
  'string' => 'string_textfield',
  'string_long' => 'string_textarea',
  'image' => 'image_image',
  'entity_reference' => 'entity_reference_autocomplete',
  'boolean' => 'boolean_checkbox',
  'integer' => 'number',
  'decimal' => 'number',
  'list_string' => 'options_select',
  'datetime' => 'datetime_default',
];

$created = ['vocabulary' => 0, 'term' => 0, 'type' => 0, 'storage' => 0, 'field' => 0];

foreach ($vocabularies as $vid => [$label, $terms]) {
  if (!Vocabulary::load($vid)) {
    Vocabulary::create(['vid' => $vid, 'name' => $label])->save();
    $created['vocabulary']++;
  }
  foreach ($terms as $name) {
    $existing = \Drupal::entityTypeManager()->getStorage('taxonomy_term')
      ->loadByProperties(['vid' => $vid, 'name' => $name]);
    if (!$existing) {
      Term::create(['vid' => $vid, 'name' => $name])->save();
      $created['term']++;
    }
  }
}

foreach ($storages as $name => $spec) {
  [$type, $cardinality] = $spec;
  $settings = $spec[2] ?? [];
  if (FieldStorageConfig::loadByName('node', $name)) {
    continue;
  }
  $values = [
    'field_name' => $name,
    'entity_type' => 'node',
    'type' => $type,
    'cardinality' => $cardinality,
  ];
  // allowed_values belongs on the storage for list fields; the rest are
  // storage settings such as decimal precision.
  if ($settings && !isset($settings['handler_settings'])) {
    $values['settings'] = $settings;
  }
  FieldStorageConfig::create($values)->save();
  $created['storage']++;
}

$formDisplayStorage = \Drupal::entityTypeManager()->getStorage('entity_form_display');

foreach ($types as $machine => [$label, $titleLabel, $description, $fields]) {
  if (!NodeType::load($machine)) {
    NodeType::create([
      'type' => $machine,
      'name' => $label,
      'description' => $description,
      'title_label' => $titleLabel,
      'new_revision' => TRUE,
      'preview_mode' => DRUPAL_OPTIONAL,
      'display_submitted' => FALSE,
    ])->save();
    $created['type']++;
  }

  $formDisplay = $formDisplayStorage->load("node.$machine.default")
    ?: $formDisplayStorage->create(['targetEntityType' => 'node', 'bundle' => $machine, 'mode' => 'default', 'status' => TRUE]);

  $weight = 0;
  foreach ($fields as $fieldName => $spec) {
    $fieldLabel = $spec[0];
    $extra = $spec[1] ?? [];
    $storage = FieldStorageConfig::loadByName('node', $fieldName);

    if (!FieldConfig::loadByName('node', $machine, $fieldName)) {
      $values = [
        'field_name' => $fieldName,
        'entity_type' => 'node',
        'bundle' => $machine,
        'label' => $fieldLabel,
        'required' => FALSE,
      ];
      if (isset($extra['handler_settings'])) {
        $values['settings'] = ['handler' => 'default:taxonomy_term', 'handler_settings' => $extra['handler_settings']];
      }
      FieldConfig::create($values)->save();
      $created['field']++;
    }

    $formDisplay->setComponent($fieldName, [
      'type' => $widgets[$storage->getType()],
      'weight' => ++$weight,
    ]);
  }
  $formDisplay->save();
}

/* ── The Partner enquiry form ──────────────────────────────────────────────
   Submissions from the React /partner page land here, so they are readable at
   /admin/structure/webform/manage/partner_enquiry/results/submissions. */
if (!\Drupal\webform\Entity\Webform::load('partner_enquiry')) {
  \Drupal\webform\Entity\Webform::create([
    'id' => 'partner_enquiry',
    'title' => 'Partner enquiry',
    'description' => 'Messages sent from the Partner With Us page on the React site.',
    'category' => 'VIA Foundation',
    'status' => 'open',
    'elements' => Yaml::encode([
      'name' => ['#type' => 'textfield', '#title' => 'Name', '#required' => TRUE],
      'email' => ['#type' => 'email', '#title' => 'Email', '#required' => TRUE],
      'organization' => ['#type' => 'textfield', '#title' => 'Organization'],
      'message' => ['#type' => 'textarea', '#title' => 'Message', '#required' => TRUE],
    ]),
  ])->save();
  $created['webform'] = 1;
}

/* ── Job applications ──────────────────────────────────────────────────────
   Submitted from a job posting's Apply form on /careers/{slug}. Résumés are
   uploaded to private:// (see settings.php) and never exposed at a public URL
   — only staff with access to view this webform's submissions can download
   one, via Drupal's own file-access check. */
if (!\Drupal\webform\Entity\Webform::load('job_application')) {
  \Drupal\webform\Entity\Webform::create([
    'id' => 'job_application',
    'title' => 'Job application',
    'description' => 'Applications submitted from a posting on the Careers page.',
    'category' => 'VIA Foundation',
    'status' => 'open',
    'elements' => Yaml::encode([
      'job_title' => ['#type' => 'textfield', '#title' => 'Position applied for', '#required' => TRUE],
      'job_slug' => ['#type' => 'textfield', '#title' => 'Job posting slug', '#required' => TRUE],
      'name' => ['#type' => 'textfield', '#title' => 'Name', '#required' => TRUE],
      'email' => ['#type' => 'email', '#title' => 'Email', '#required' => TRUE],
      'phone' => ['#type' => 'tel', '#title' => 'Phone'],
      'portfolio_url' => ['#type' => 'url', '#title' => 'LinkedIn / portfolio link'],
      'cover_message' => ['#type' => 'textarea', '#title' => 'Why are you a good fit for this role?', '#required' => TRUE],
      'resume' => [
        '#type' => 'managed_file',
        '#title' => 'Résumé / CV',
        '#required' => TRUE,
        '#uri_scheme' => 'private',
        '#file_extensions' => 'pdf doc docx',
        '#max_filesize' => '10 MB',
        '#upload_location' => 'private://job-applications',
      ],
    ]),
  ])->save();
  $created['webform'] = ($created['webform'] ?? 0) + 1;
}

/* ── Whistleblower / Report a Concern ─────────────────────────────────────
   form_disable_remote_addr is set for every submission to this webform, not
   only anonymous ones — see WebformSubmission::preCreate()/save(), which
   reads exactly this flag to decide whether to record the request's IP at
   all. A report made "with contact details" still isn't a report that needs
   an IP on file, and only setting it conditionally would mean a frontend bug
   in the anonymous toggle could leak one; this way there's no toggle to get
   wrong; see src/app/pages/WhistleblowerPage.tsx / via_api's
   WhistleblowerController for the rest of the confidentiality handling. */
if (!\Drupal\webform\Entity\Webform::load('whistleblower_report')) {
  \Drupal\webform\Entity\Webform::create([
    'id' => 'whistleblower_report',
    'title' => 'Whistleblower report',
    'description' => 'Concerns submitted through /report-a-concern. Never records a submitter IP address — see form_disable_remote_addr below.',
    'category' => 'VIA Foundation',
    'status' => 'open',
    'elements' => Yaml::encode([
      'anonymous' => [
        '#type' => 'checkbox',
        '#title' => 'Submitted anonymously',
      ],
      'reporter_name' => ['#type' => 'textfield', '#title' => 'Name'],
      'reporter_email' => ['#type' => 'email', '#title' => 'Email'],
      'concern_types' => [
        '#type' => 'checkboxes',
        '#title' => 'What does this concern relate to?',
        '#required' => TRUE,
        '#options' => [
          'fraud_financial' => 'Fraud or financial misconduct',
          'corruption_bribery' => 'Corruption or bribery',
          'conflict_of_interest' => 'Conflict of interest',
          'safeguarding' => 'Safeguarding, abuse or exploitation',
          'sexual_harassment' => 'Sexual harassment or misconduct',
          'discrimination' => 'Discrimination or harassment',
          'data_privacy' => 'Data privacy or security breach',
          'environmental_safety' => 'Environmental or safety violation',
          'other' => 'Other',
        ],
      ],
      'related_project_or_office' => ['#type' => 'textfield', '#title' => 'Project or office involved (if known)'],
      'person_involved' => ['#type' => 'textfield', '#title' => 'Person(s) involved (if known)'],
      'incident_date' => ['#type' => 'date', '#title' => 'Date of incident (if known)'],
      'description' => ['#type' => 'textarea', '#title' => 'What happened?', '#required' => TRUE],
      'attachments' => [
        '#type' => 'managed_file',
        '#title' => 'Supporting documents (optional, up to 5 files)',
        '#multiple' => 5,
        '#uri_scheme' => 'private',
        '#file_extensions' => 'pdf doc docx jpg jpeg png',
        '#max_filesize' => '10 MB',
        '#upload_location' => 'private://whistleblower-reports',
      ],
    ]),
    'settings' => [
      'form_disable_remote_addr' => TRUE,
      'confirmation_type' => 'inline',
      'confirmation_message' => "Thank you — your report has been received in confidence and will be reviewed by VIA Foundation's safeguarding lead. If you shared contact details we will follow up directly. This report is not linked to an IP address, whether or not you submitted it anonymously.",
    ],
  ])->save();
  $created['webform'] = ($created['webform'] ?? 0) + 1;
}

echo "Content model built.\n";
foreach ($created as $what => $n) {
  echo sprintf("  %-11s created: %d\n", $what, $n);
}
