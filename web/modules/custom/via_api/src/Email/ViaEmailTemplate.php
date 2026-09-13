<?php

namespace Drupal\via_api\Email;

use Drupal\via_api\ViaMail;

/**
 * Renders one branded email from a plain content spec — no Twig, since these
 * go through Symfony Mailer directly rather than Drupal's render pipeline
 * (see ViaMail and ViaHtmlMailer). HTML email needs table layout and inline
 * styles to survive Outlook, so this is written by hand rather than adapted
 * from the site's own Tailwind markup — but it reuses the same palette, type
 * roles and shapes (a slim green masthead carrying the logo, gold accents,
 * left-aligned content on white — the same clean transactional shape as a
 * Cloudflare/Stripe-style receipt email) so an email reads as a professional,
 * on-brand piece of mail rather than a generic system notice.
 *
 * Every string a visitor typed (name, message, email) MUST go through esc()
 * before it lands in the HTML — see the callers in ViaMail::spec().
 */
final class ViaEmailTemplate {

  private const GREEN_HEADER = '#123524';
  private const GREEN_ACCENT = '#56B44B';
  private const GOLD = '#C8A45A';
  private const CREAM = '#F5F2EA';
  private const INK = '#1A1A1A';
  private const BODY_TEXT = '#3F3F3F';
  private const MUTED = '#7A7A7A';
  private const HAIRLINE = '#E7E4DC';

  private const FONT_HEADING = "'Manrope', Helvetica, Arial, sans-serif";
  private const FONT_BODY = "'Inter', Helvetica, Arial, sans-serif";

  /** Escapes a value for use in the HTML body — call this on any submitted content. */
  public static function esc(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
  }

  /** Same text, with line breaks turned into <br> — for a submitted message. */
  public static function escMultiline(string $value): string {
    return nl2br(self::esc($value), FALSE);
  }

  /**
   * Renders the full HTML document.
   *
   * $spec keys (see ViaMail::spec() for how each email builds one):
   *   preheader    string — inbox preview text, invisible in the body itself.
   *   eyebrow      string|null — already-escaped HTML, small label above the
   *                heading (e.g. "PARTNER WITH US") — the same role as
   *                PageHero's eyebrow on the website.
   *   heading      string — already-escaped HTML (may contain a first name).
   *   intro        string[] — paragraphs, already-escaped HTML.
   *   details      array<array{0: string, 1: string}>|null — label/value
   *                rows, already-escaped HTML values, shown as a bulleted list.
   *   quote        array{label: string, html: string}|null — the quote/
   *                message block; html already escMultiline()'d.
   *   primaryCta   array{label: string, url: string}|null
   *   secondaryCta array{label: string, url: string}|null
   *   whyReceiving string|null — already-escaped HTML. Rendered as its own
   *                labelled section ("Why am I receiving this email?"),
   *                same as a billing platform explains an invoice notice.
   */
  public static function render(array $spec): string {
    $preheader = self::esc($spec['preheader'] ?? '');
    $intro = implode('', array_map(
      fn($p) => '<p style="margin:0 0 16px;color:' . self::BODY_TEXT . ';font-family:' . self::FONT_BODY . ';font-size:15px;line-height:1.7;">' . $p . '</p>',
      $spec['intro'] ?? []
    ));
    $details = self::renderDetails($spec['details'] ?? NULL);
    $quote = self::renderQuote($spec['quote'] ?? NULL);
    $ctas = self::renderCtas($spec['primaryCta'] ?? NULL, $spec['secondaryCta'] ?? NULL);
    $why = !empty($spec['whyReceiving']) ? self::renderWhy($spec['whyReceiving']) : '';

    $siteRoot = ViaMail::frontendUrl('/');
    $siteHost = (string) parse_url($siteRoot, PHP_URL_HOST);
    $headerGreen = self::GREEN_HEADER;
    $accent = self::GREEN_ACCENT;
    $gold = self::GOLD;
    $muted = self::MUTED;
    $hairline = self::HAIRLINE;
    $fontHeading = self::FONT_HEADING;
    $fontBody = self::FONT_BODY;
    $heading = $spec['heading'];
    $eyebrow = !empty($spec['eyebrow'])
      ? '<div style="margin:0 0 10px;color:' . $accent . ';font-family:' . $fontHeading . ';font-weight:800;font-size:11px;letter-spacing:.16em;text-transform:uppercase;">' . $spec['eyebrow'] . '</div>'
      : '';
    $year = date('Y');

    // A table-based layout, inline styles throughout: the only way HTML email
    // survives Outlook's Word rendering engine as well as Gmail/Apple Mail.
    // White page, a slim green masthead (guarantees contrast for the white
    // logo mark) and left-aligned content — a plain, professional shape
    // rather than a floating card, closer to how a billing or account
    // notification from a well-run product reads.
    return <<<HTML
<!doctype html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<title>VIA Foundation</title>
<!--[if mso]>
<style>* { font-family: Arial, sans-serif !important; }</style>
<![endif]-->
</head>
<body style="margin:0;padding:0;background-color:#ffffff;">
<div style="display:none;max-height:0;overflow:hidden;opacity:0;mso-hide:all;">{$preheader}&#8203;&nbsp;&zwnj;&nbsp;&#8203;</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#ffffff;">
<tr><td align="center">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:600px;">

<tr><td style="background-color:{$gold};line-height:4px;font-size:4px;">&nbsp;</td></tr>

<tr><td align="left" style="background-color:{$headerGreen};padding:18px 32px;">
<img src="cid:via-logo" width="118" alt="VIA Foundation" style="display:block;border:0;outline:none;text-decoration:none;">
</td></tr>

<tr><td style="padding:40px 32px 8px;">
{$eyebrow}
<h1 style="margin:0 0 20px;color:#1A1A1A;font-family:{$fontHeading};font-weight:800;font-size:25px;line-height:1.3;letter-spacing:-.02em;">{$heading}</h1>
{$intro}
{$details}
{$quote}
{$ctas}
{$why}
</td></tr>

<tr><td style="padding:8px 32px 0;"><div style="height:1px;background-color:{$hairline};line-height:1px;font-size:1px;">&nbsp;</div></td></tr>

<tr><td align="center" style="padding:28px 32px 36px;">
<div style="color:{$headerGreen};font-family:{$fontHeading};font-weight:800;font-size:14px;letter-spacing:.01em;">VIA Foundation</div>
<div style="margin-top:3px;color:{$muted};font-family:{$fontBody};font-size:11px;letter-spacing:.03em;text-transform:uppercase;">Vumbuzi Impact Africa Foundation</div>
<div style="margin-top:14px;color:{$muted};font-family:{$fontBody};font-size:12px;line-height:1.9;">
Kigali, Rwanda&nbsp; &middot; &nbsp;<a href="mailto:info@via-foundation.org" style="color:{$accent};text-decoration:none;">info@via-foundation.org</a>&nbsp; &middot; &nbsp;<a href="{$siteRoot}" style="color:{$accent};text-decoration:none;">{$siteHost}</a>
</div>
<div style="margin-top:16px;color:#B9B4A8;font-family:{$fontBody};font-size:11px;">&copy; {$year} VIA Foundation. All rights reserved.</div>
</td></tr>

</table>
</td></tr>
</table>
</body>
</html>
HTML;
  }

  /** The plain-text alternative — same content, no markup, for clients that show it. */
  public static function renderText(array $spec): string {
    $lines = [strip_tags($spec['heading']), ''];
    foreach ($spec['intro'] ?? [] as $p) {
      $lines[] = strip_tags($p);
      $lines[] = '';
    }
    if (!empty($spec['details'])) {
      foreach ($spec['details'] as [$label, $value]) {
        $lines[] = '- ' . strip_tags($label) . ': ' . strip_tags($value);
      }
      $lines[] = '';
    }
    if (!empty($spec['quote'])) {
      $lines[] = strip_tags($spec['quote']['label']) . ':';
      $plain = trim(preg_replace('/\s*<br\s*\/?>\s*/i', "\n", $spec['quote']['html']));
      $lines[] = '"' . strip_tags($plain) . '"';
      $lines[] = '';
    }
    foreach (['primaryCta', 'secondaryCta'] as $key) {
      if (!empty($spec[$key])) {
        $lines[] = $spec[$key]['label'] . ': ' . $spec[$key]['url'];
      }
    }
    if (!empty($spec['whyReceiving'])) {
      $lines[] = '';
      $lines[] = 'Why am I receiving this email?';
      $lines[] = strip_tags($spec['whyReceiving']);
    }
    $lines[] = '';
    $lines[] = '—';
    $lines[] = 'VIA Foundation (Vumbuzi Impact Africa Foundation)';
    $lines[] = 'Kigali, Rwanda';
    $lines[] = 'info@via-foundation.org';
    return implode("\n", $lines);
  }

  /** A clean bulleted list — "Name: Amina Njau" per line — not a two-column table. */
  private static function renderDetails(?array $rows): string {
    if (!$rows) {
      return '';
    }
    $items = '';
    foreach ($rows as [$label, $value]) {
      $items .= '<tr><td style="padding:0 0 8px;vertical-align:top;width:18px;color:' . self::GREEN_ACCENT . ';font-family:' . self::FONT_BODY . ';font-size:15px;line-height:1.6;">&bull;</td>'
        . '<td style="padding:0 0 8px;vertical-align:top;color:' . self::BODY_TEXT . ';font-family:' . self::FONT_BODY . ';font-size:14px;line-height:1.6;"><strong style="color:' . self::INK . ';">' . $label . ':</strong> ' . $value . '</td></tr>';
    }
    return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:4px 0 22px;">' . $items . '</table>';
  }

  private static function renderQuote(?array $quote): string {
    if (!$quote) {
      return '';
    }
    return '<div style="margin:0 0 24px;padding:16px 20px;background-color:' . self::CREAM . ';border-left:3px solid ' . self::GOLD . ';border-radius:0 6px 6px 0;">'
      . '<div style="margin:0 0 6px;color:' . self::GREEN_ACCENT . ';font-family:' . self::FONT_HEADING . ';font-weight:800;font-size:10px;letter-spacing:.12em;text-transform:uppercase;">' . $quote['label'] . '</div>'
      . '<div style="color:' . self::INK . ';font-family:' . self::FONT_BODY . ';font-size:14px;line-height:1.7;font-style:italic;">' . $quote['html'] . '</div>'
      . '</div>';
  }

  private static function renderCtas(?array $primary, ?array $secondary): string {
    if (!$primary && !$secondary) {
      return '';
    }
    // Left-aligned, under the content — not centered — matching the rest of
    // the layout, and a rectangular (not pill) button for the flatter,
    // more corporate tone this version aims for.
    $html = '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:8px 0 20px;"><tr>';
    if ($primary) {
      $html .= '<td align="center" bgcolor="' . self::GREEN_ACCENT . '" style="border-radius:6px;">'
        . '<a href="' . $primary['url'] . '" style="display:inline-block;padding:13px 28px;color:#ffffff;font-family:' . self::FONT_HEADING . ';font-weight:700;font-size:14px;text-decoration:none;border-radius:6px;">' . $primary['label'] . '</a>'
        . '</td>';
    }
    $html .= '</tr></table>';
    if ($secondary) {
      $html .= '<div style="margin:0 0 22px;"><a href="' . $secondary['url'] . '" style="color:' . self::GREEN_ACCENT . ';font-family:' . self::FONT_HEADING . ';font-weight:700;font-size:13px;text-decoration:none;">' . $secondary['label'] . ' &rarr;</a></div>';
    }
    return $html;
  }

  /** A labelled explanation block, the same way a billing platform explains a notice — not tiny footer print. */
  private static function renderWhy(string $html): string {
    return '<div style="margin:8px 0 4px;padding-top:20px;border-top:1px solid ' . self::HAIRLINE . ';">'
      . '<div style="margin:0 0 6px;color:' . self::INK . ';font-family:' . self::FONT_HEADING . ';font-weight:700;font-size:13px;">Why am I receiving this email?</div>'
      . '<div style="color:' . self::MUTED . ';font-family:' . self::FONT_BODY . ';font-size:13px;line-height:1.6;">' . $html . '</div>'
      . '</div>';
  }

}
