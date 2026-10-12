<?php

declare(strict_types=1);

namespace SilverShop\CookieConsent\View;

use SilverShop\CookieConsent\ConsentState;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\View\TemplateGlobalProvider;

/**
 * Exposes the consent-state API to every template — including the theme's
 * page-less header / footer shells, which are not bound to a page model:
 *
 *   <% if $HasConsent('statistics') %> ... <% end_if %>
 *   <% if $CookieConsentNeedsPrompt %> ... <% end_if %>
 *
 * Auto-registered by implementing {@link TemplateGlobalProvider} (no YAML).
 */
class ConsentTemplateProvider implements TemplateGlobalProvider
{
    public static function get_template_global_variables(): array
    {
        return [
            'HasConsent' => 'HasConsent',
            'CookieConsentNeedsPrompt' => 'NeedsPrompt',
            'CookieConsentVersion' => 'Version',
        ];
    }

    /**
     * Does the visitor consent to a category? When consent management is off,
     * everything is treated as granted so gated regions still render.
     */
    public static function HasConsent(string $category = ConsentState::NECESSARY): bool
    {
        if (!SiteConfig::current_site_config()->CookieConsentActive) {
            return true;
        }
        return ConsentState::has($category);
    }

    public static function NeedsPrompt(): bool
    {
        if (!SiteConfig::current_site_config()->CookieConsentActive) {
            return false;
        }
        return ConsentState::needsPrompt();
    }

    public static function Version(): int
    {
        return ConsentState::version();
    }
}
