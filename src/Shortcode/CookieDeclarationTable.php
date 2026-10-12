<?php

declare(strict_types=1);

namespace SilverShop\CookieConsent\Shortcode;

use SilverShop\CookieConsent\Model\CookieCategory;
use SilverStripe\CMS\Controllers\ContentController;
use SilverStripe\Control\Controller;
use SilverStripe\View\Parsers\ShortcodeParser;

/**
 * Renders the cookie declaration table for one category, or for every active
 * category when no `category` argument is given.
 *
 * Usage in content:
 *   [cookie_declaration]                    all active categories
 *   [cookie_declaration category="statistics"]  one category
 */
class CookieDeclarationTable
{
    public static function register(): void
    {
        ShortcodeParser::get('default')->register('cookie_declaration', [self::class, 'parse']);
    }

    /**
     * @param array<string,string> $arguments
     */
    public static function parse(
        array $arguments,
        ?string $content,
        ShortcodeParser $parser,
        string $shortcode
    ): ?string {
        $categoryKey = $arguments['category'] ?? '';

        $template = 'SilverShop\\CookieConsent\\Includes\\CookieDeclarationTable';

        if ($categoryKey !== '') {
            $category = CookieCategory::get()->find('Code', $categoryKey);
            if ($category) {
                return (string) $category->renderWith($template)->getValue();
            }
            // Keep the raw shortcode in the CMS so it is not lost; hide on frontend.
            return Controller::curr() instanceof ContentController
                ? null
                : "[cookie_declaration category=\"$categoryKey\"]";
        }

        $html = '';
        foreach (CookieCategory::get()->filter(['Active' => 1]) as $category) {
            if (!$category->Cookies()->exists()) {
                continue;
            }
            $html .= (string) $category->renderWith($template)->getValue();
        }
        return $html;
    }
}
