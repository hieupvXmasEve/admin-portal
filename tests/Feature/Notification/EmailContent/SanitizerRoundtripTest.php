<?php

declare(strict_types=1);

use App\Modules\Notification\Support\NotificationEmailTemplateProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mews\Purifier\Facades\Purifier;

/**
 * B2 exit-state proof: mews/purifier email_body profile round-trip + XSS strip.
 *
 * Round-trip strategy (test a):
 *   The seeded body_html values are full HTML documents (<!DOCTYPE html> …
 *   </html>). HTMLPurifier in fragment mode strips the outer document wrapper
 *   and returns only the inner content. In production the admin editor
 *   (TipTap/rich-text) saves fragments — NOT full documents — so Purifier is
 *   applied to fragments only.
 *
 *   Therefore the round-trip test extracts the <body> inner content from each
 *   provisioner default, sanitizes the fragment, then asserts byte-equivalence
 *   after whitespace normalisation. This proves the email_body allow-list
 *   preserves inline style=, table structures, and UTF-8 characters without
 *   stripping anything the admin legitimately authored.
 *
 *   Whitespace + CSS normalisation: HTMLPurifier reformats CSS values inside
 *   style= attributes (e.g. `color: rgb(0, 0, 0)` → `color:rgb(0,0,0)` — spaces
 *   after colons and commas are removed by HTMLPurifier's CSS validator). The
 *   sanitizerNormalize() function therefore also strips spaces after CSS
 *   punctuation inside style=" …" blocks so both sides compare equally.
 */
uses(RefreshDatabase::class);

/**
 * Extract the fragment between the first <body ...> and </body> in a full HTML
 * document. Returns the trimmed inner content.
 */
function bodyFragment(string $fullDocument): string
{
    // Match everything between opening <body ...> tag and </body>
    if (preg_match('/<body[^>]*>(.*?)<\/body>/si', $fullDocument, $matches) === 1) {
        return trim($matches[1]);
    }

    // Fallback: return as-is if no body wrapper found (fragment already)
    return trim($fullDocument);
}

/**
 * Normalise HTML for round-trip comparison.
 *
 * Steps:
 *  1. Strip spaces after `:` and `,` inside CSS style= values.
 *     HTMLPurifier's CSS validator removes these spaces (e.g.
 *     `color: rgb(0, 0, 0)` → `color:rgb(0,0,0)`), so we normalise both
 *     sides before comparing.
 *  2. Collapse all whitespace runs to a single space and trim.
 *     Matches parityNormalize() in DbEmailContentParityTest.
 */
function sanitizerNormalize(string $value): string
{
    // Normalise CSS inside style="..." attributes.
    // HTMLPurifier's CSS validator removes spaces after `:`, `,`, and `;` in
    // inline style values (e.g. `border-collapse: collapse; width: 100%` →
    // `border-collapse:collapse;width:100%`). We apply the same stripping to
    // both sides so the comparison is format-agnostic.
    $cssCleaned = preg_replace_callback(
        '/style="([^"]*)"/i',
        static function (array $m): string {
            $css = $m[1];
            // Remove spaces after colon (property: value → property:value)
            $css = preg_replace('/:\s+/', ':', (string) $css);
            // Remove spaces after comma (rgb(0, 0, 0) → rgb(0,0,0))
            $css = preg_replace('/,\s+/', ',', (string) $css);
            // Remove spaces after semicolon (prop:val; prop2:val → prop:val;prop2:val)
            $css = preg_replace('/;\s+/', ';', (string) $css);

            return 'style="'.$css.'"';
        },
        $value,
    );

    // HTMLPurifier converts &nbsp; entities to the UTF-8 non-breaking space
    // character (\xC2\xA0). Normalise both sides to a regular space so the
    // comparison is not sensitive to this entity/codepoint distinction.
    $noNbsp = str_replace(['&nbsp;', "\xC2\xA0"], ' ', (string) $cssCleaned);

    // HTMLPurifier may reorder values inside rel="..." attributes (e.g.
    // "noopener noreferrer nofollow" → "nofollow noopener noreferrer").
    // Sort values alphabetically so ordering differences do not cause failures.
    $relNorm = preg_replace_callback(
        '/rel="([^"]*)"/i',
        static function (array $m): string {
            $vals = preg_split('/\s+/', trim($m[1]));
            sort($vals);

            return 'rel="'.implode(' ', (array) $vals).'"';
        },
        $noNbsp,
    );

    // HTMLPurifier serialises void elements as self-closing (e.g. <hr /> while
    // the source HTML may use <hr>). Normalise to the self-closing form.
    $hrNorm = preg_replace('/<hr([^>]*[^\/])>/i', '<hr$1 />', (string) $relNorm);

    $collapsed = preg_replace('/\s+/', ' ', (string) $hrNorm);

    return trim((string) $collapsed);
}

/**
 * (a) Round-trip parity: for each of the 4 provisioner defaults, the body
 * fragment must survive Purifier::clean($fragment, 'email_body') unchanged
 * (after whitespace normalisation).
 *
 * Tests are data-driven from NotificationEmailTemplateProvisioner::defaults()
 * via reflection, so they stay in sync if the provisioner bodies are updated.
 */
it('round-trips each seeded email body fragment through the email_body purifier profile unchanged', function (string $typeKey) {
    $provisioner = app(NotificationEmailTemplateProvisioner::class);

    // Access defaults via reflection — they are private but the test needs them
    // as the ground-truth fixture; avoids duplicating large HTML literals here.
    $reflection = new ReflectionMethod($provisioner, 'defaults');
    $reflection->setAccessible(true);
    /** @var array<string, array{subject: string, body_html: string}> $defaults */
    $defaults = $reflection->invoke($provisioner);

    $fullDocument = $defaults[$typeKey]['body_html'];
    $fragment = bodyFragment($fullDocument);

    $sanitized = Purifier::clean($fragment, 'email_body');

    expect(sanitizerNormalize($sanitized))
        ->toBe(
            sanitizerNormalize($fragment),
            "Purifier stripped content from the '{$typeKey}' email body fragment.\n"
            ."Original (normalised):\n".sanitizerNormalize($fragment)."\n\n"
            ."Sanitized (normalised):\n".sanitizerNormalize($sanitized),
        );
})->with([
    'payment_reminder' => ['payment_reminder'],
    'parent_payment_reminder' => ['parent_payment_reminder'],
    'dng_payment_pushed' => ['dng_payment_pushed'],
    'dng_payment_received' => ['dng_payment_received'],
]);

/**
 * (b) XSS strip: a fixture body containing the three canonical XSS vectors
 * must be sanitized so that the dangerous constructs are removed.
 *
 * Asserts the sanitized output does NOT contain:
 *   - <script  (case-insensitive)
 *   - javascript:
 *   - onerror=
 *
 * Note (Critical Pattern #1): the fixture intentionally embeds unsafe
 * characters (<, >, ") to ensure the sanitizer is exercised on real attack
 * vectors, not safe-char inputs that would false-green.
 */
it('strips XSS vectors from email body HTML', function () {
    $xssFixture = implode("\n", [
        '<p>Normal paragraph.</p>',
        '<script>alert(1)</script>',
        '<a href="javascript:alert(1)">click me</a>',
        '<img src=x onerror=alert(1)>',
        '<p style="color: red;">Safe styled paragraph.</p>',
    ]);

    $sanitized = Purifier::clean($xssFixture, 'email_body');

    // Must not contain any of the three XSS signals
    expect(strtolower($sanitized))
        ->not->toContain('<script', 'script tag must be stripped')
        ->not->toContain('javascript:', 'javascript: URI scheme must be stripped')
        ->not->toContain('onerror=', 'onerror event handler must be stripped');

    // Safe content must still be present
    expect($sanitized)
        ->toContain('Normal paragraph')
        ->toContain('Safe styled paragraph');
});
