<?php

declare(strict_types=1);

namespace SilverShop\CookieConsent\Shortcode;

use SilverShop\CookieConsent\ConsentState;
use SilverShop\CookieConsent\View\ConsentScript;
use SilverStripe\Model\ArrayData;
use SilverStripe\View\Parsers\ShortcodeParser;
use SilverStripe\View\SSViewer;

/**
 * Click-to-load placeholder for third-party embeds (YouTube, Maps, ...). Renders
 * a neutral placeholder until the required consent category is granted; the
 * client runtime then swaps in the real iframe (automatically on consent, or when
 * the visitor clicks "load" / "always allow").
 *
 * Usage in content:
 *   [consent_embed src="https://www.youtube.com/embed/xyz" category="marketing" title="Video"]
 */
class ConsentEmbed
{
    public static function register(): void
    {
        ShortcodeParser::get('default')->register('consent_embed', [self::class, 'parse']);
    }

    /**
     * @param array<string,string> $arguments
     */
    public static function parse(array $arguments, ?string $content, ShortcodeParser $parser, string $shortcode): string
    {
        $src = isset($arguments['src']) ? trim((string) $arguments['src']) : '';
        if ($src === '') {
            return '';
        }

        $category = !empty($arguments['category'])
            ? ConsentScript::sanitiseCategory((string) $arguments['category'])
            : ConsentState::MARKETING;

        $data = ArrayData::create([
            'EmbedSrc' => $src,
            'Category' => $category,
            'EmbedTitle' => $arguments['title'] ?? '',
            'Ratio' => !empty($arguments['ratio']) ? $arguments['ratio'] : '16x9',
        ]);

        return (string) SSViewer::create('SilverShop\\CookieConsent\\Shortcode\\ConsentEmbed')->process($data);
    }
}
