<?php

namespace Drupal\via_api\Email;

use Drupal\via_api\ViaMail;

/**
 * Renders one branded email from a plain content spec — no Twig, since these
 * go through Symfony Mailer directly rather than Drupal's render pipeline
 * (see ViaMail and ViaHtmlMailer). HTML email needs table layout and inline
 * styles to survive Outlook, so this is written by hand rather than adapted
 * from the site's own Tailwind markup — but it reuses the same palette, type
 * roles and shapes (pill buttons, gold-accent quote block, dark green
 * header/footer) so an email reads as the same product as the website.
 *
 * Every string a visitor typed (name, message, email) MUST go through esc()
 * before it lands in the HTML — see the callers in ViaMail::spec().
 */
final class ViaEmailTemplate {

  private const GREEN_HEADER = '#123524';
  private const GREEN_FOOTER = '#0d2218';
  private const GREEN_ACCENT = '#56B44B';
  private const GOLD = '#C8A45A';
  private const CREAM = '#F5F2EA';
  private const INK = '#333333';
  private const BODY_TEXT = '#4A4A4A';

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
   *   heading      string — already-escaped HTML (may contain a first name).
   *   intro        string[] — paragraphs, already-escaped HTML.
   *   details      array<array{0: string, 1: string}>|null — label/value
   *                rows, already-escaped HTML values.
   *   quote        array{label: string, html: string}|null — the quote/
   *                message block; html already escMultiline()'d.
   *   primaryCta   array{label: string, url: string}|null
   *   secondaryCta array{label: string, url: string}|null
   *   footerNote   string|null — already-escaped HTML, small print under the
   *                address (e.g. why a newsletter subscriber got this).
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
    $footerNote = !empty($spec['footerNote'])
      ? '<p style="margin:18px 0 0;color:rgba(255,255,255,.4);font-family:' . self::FONT_BODY . ';font-size:11px;line-height:1.6;font-style:italic;">' . $spec['footerNote'] . '</p>'
      : '';

    $siteRoot = ViaMail::frontendUrl('/');
    $siteHost = (string) parse_url($siteRoot, PHP_URL_HOST);
    $headerGreen = self::GREEN_HEADER;
    $footerGreen = self::GREEN_FOOTER;
    $accent = self::GREEN_ACCENT;
    $fontHeading = self::FONT_HEADING;
    $fontBody = self::FONT_BODY;
    $heading = $spec['heading'];
    $year = date('Y');

    // A table-based layout, inline styles throughout: the only way HTML email
    // survives Outlook's Word rendering engine as well as Gmail/Apple Mail.
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
<body style="margin:0;padding:0;background-color:#EAE6DA;">
<div style="display:none;max-height:0;overflow:hidden;opacity:0;mso-hide:all;">{$preheader}&#8203;&nbsp;&zwnj;&nbsp;&#8203;</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#EAE6DA;">
<tr><td align="center" style="padding:32px 16px;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:600px;background-color:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #E5E1D8;">

<tr><td align="center" style="background-color:{$headerGreen};padding:30px 32px;">
<img src="cid:via-logo" width="132" alt="VIA Foundation" style="display:block;border:0;outline:none;text-decoration:none;">
</td></tr>

<tr><td style="padding:40px 40px 8px;">
<h1 style="margin:0 0 18px;color:{$headerGreen};font-family:{$fontHeading};font-weight:800;font-size:24px;line-height:1.25;letter-spacing:-.02em;">{$heading}</h1>
{$intro}
{$details}
{$quote}
{$ctas}
</td></tr>

<tr><td style="padding:8px 40px 0;"><div style="height:1px;background-color:#EDEAE1;line-height:1px;font-size:1px;">&nbsp;</div></td></tr>

<tr><td align="center" style="background-color:{$footerGreen};padding:30px 32px;">
<div style="color:#ffffff;font-family:{$fontHeading};font-weight:800;font-size:14px;letter-spacing:.02em;">VIA Foundation</div>
<div style="margin-top:4px;color:rgba(255,255,255,.45);font-family:{$fontBody};font-size:11px;letter-spacing:.04em;text-transform:uppercase;">Vumbuzi Impact Africa Foundation</div>
<div style="margin-top:16px;color:rgba(255,255,255,.55);font-family:{$fontBody};font-size:12px;line-height:1.8;">
Kigali, Rwanda&nbsp; &middot; &nbsp;<a href="mailto:info@via-foundation.org" style="color:{$accent};text-decoration:none;">info@via-foundation.org</a>&nbsp; &middot; &nbsp;<a href="{$siteRoot}" style="color:{$accent};text-decoration:none;">{$siteHost}</a>
</div>
{$footerNote}
<div style="margin-top:20px;color:rgba(255,255,255,.28);font-family:{$fontBody};font-size:11px;">&copy; {$year} VIA Foundation. All rights reserved.</div>
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
        $lines[] = strip_tags($label) . ': ' . strip_tags($value);
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
    $lines[] = '';
    $lines[] = '—';
    $lines[] = 'VIA Foundation (Vumbuzi Impact Africa Foundation)';
    $lines[] = 'Kigali, Rwanda';
    $lines[] = 'info@via-foundation.org';
    if (!empty($spec['footerNote'])) {
      $lines[] = '';
      $lines[] = strip_tags($spec['footerNote']);
    }
    return implode("\n", $lines);
  }

  private static function renderDetails(?array $rows): string {
    if (!$rows) {
      return '';
    }
    $cells = '';
    foreach ($rows as [$label, $value]) {
      $cells .= '<tr>'
        . '<td style="padding:0 0 12px;vertical-align:top;width:120px;color:' . self::GREEN_ACCENT . ';font-family:' . self::FONT_HEADING . ';font-weight:800;font-size:10px;letter-spacing:.12em;text-transform:uppercase;">' . $label . '</td>'
        . '<td style="padding:0 0 12px;vertical-align:top;color:' . self::INK . ';font-family:' . self::FONT_BODY . ';font-size:14px;font-weight:600;">' . $value . '</td>'
        . '</tr>';
    }
    return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:4px 0 20px;">' . $cells . '</table>';
  }

  private static function renderQuote(?array $quote): string {
    if (!$quote) {
      return '';
    }
    return '<div style="margin:0 0 24px;padding:16px 20px;background-color:' . self::CREAM . ';border-left:3px solid ' . self::GOLD . ';border-radius:0 8px 8px 0;">'
      . '<div style="margin:0 0 6px;color:' . self::GREEN_ACCENT . ';font-family:' . self::FONT_HEADING . ';font-weight:800;font-size:10px;letter-spacing:.12em;text-transform:uppercase;">' . $quote['label'] . '</div>'
      . '<div style="color:' . self::INK . ';font-family:' . self::FONT_BODY . ';font-size:14px;line-height:1.7;font-style:italic;">' . $quote['html'] . '</div>'
      . '</div>';
  }

  private static function renderCtas(?array $primary, ?array $secondary): string {
    if (!$primary && !$secondary) {
      return '';
    }
    $html = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:8px 0 4px;"><tr><td align="center">';
    if ($primary) {
      $html .= '<table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr><td align="center" bgcolor="' . self::GREEN_ACCENT . '" style="border-radius:999px;">'
        . '<a href="' . $primary['url'] . '" style="display:inline-block;padding:14px 34px;color:#ffffff;font-family:' . self::FONT_HEADING . ';font-weight:700;font-size:14px;text-decoration:none;border-radius:999px;">' . $primary['label'] . ' &rarr;</a>'
        . '</td></tr></table>';
    }
    if ($secondary) {
      $html .= '<div style="margin-top:16px;"><a href="' . $secondary['url'] . '" style="color:' . self::GREEN_ACCENT . ';font-family:' . self::FONT_HEADING . ';font-weight:700;font-size:13px;text-decoration:none;">' . $secondary['label'] . ' &rarr;</a></div>';
    }
    $html .= '</td></tr></table>';
    return $html;
  }

}
