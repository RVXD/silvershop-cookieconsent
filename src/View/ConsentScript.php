<?php

declare(strict_types=1);

namespace SilverShop\CookieConsent\View;

use SilverShop\CookieConsent\ConsentState;
use SilverStripe\ORM\FieldType\DBHTMLText;

/**
 * Emits third-party tags as *inert* scripts that only run once the matching
 * consent category is granted. This is true prior blocking (ePrivacy "prior
 * opt-in"), not just Consent Mode signalling.
 *
 * The markup follows the widely-used convention
 * `<script type="text/plain" data-consent-category="statistics">`; the client
 * runtime activates it on load and on every `consentchange`. Any script carrying
 * these attributes is handled however it was produced — including a vendor
 * snippet a CMS editor marked by hand.
 */
class ConsentScript
{
    /**
     * Emit an inline script gated on a category.
     *
     * NOTE: the JS body must not contain the literal `</script>` sequence.
     */
    public static function inline(string $js, string $category = ConsentState::STATISTICS): DBHTMLText
    {
        $html = sprintf(
            '<script type="text/plain" data-consent-category="%s">%s</script>',
            self::sanitiseCategory($category),
            $js
        );
        return DBHTMLText::create()->setValue($html);
    }

    /**
     * Emit an inert external script gated on a category. The real URL is parked in
     * `data-src` and promoted to `src` on activation, so preload scanners do not
     * fetch it early.
     *
     * @param array<string,mixed> $attrs extra attributes, e.g. ['async' => true, 'id' => 'x']
     */
    public static function src(string $url, string $category = ConsentState::STATISTICS, array $attrs = []): DBHTMLText
    {
        $extra = '';
        foreach ($attrs as $name => $value) {
            $name = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $name);
            if ($value === true) {
                $extra .= ' ' . $name;
            } elseif ($value !== false && $value !== null) {
                $extra .= sprintf(' %s="%s"', $name, htmlspecialchars((string) $value, ENT_QUOTES));
            }
        }
        $html = sprintf(
            '<script type="text/plain" data-consent-category="%s" data-src="%s"%s></script>',
            self::sanitiseCategory($category),
            htmlspecialchars($url, ENT_QUOTES),
            $extra
        );
        return DBHTMLText::create()->setValue($html);
    }

    /**
     * Sanitise a category key for use in an attribute; falls back to statistics.
     */
    public static function sanitiseCategory(string $category): string
    {
        $category = (string) preg_replace('/[^a-zA-Z0-9_-]/', '', $category);
        return $category !== '' ? $category : ConsentState::STATISTICS;
    }
}
