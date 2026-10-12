<?php

declare(strict_types=1);

namespace SilverShop\CookieConsent\Tests;

use SilverShop\CookieConsent\ConsentState;
use SilverShop\CookieConsent\Shortcode\ConsentEmbed;
use SilverShop\CookieConsent\View\ConsentTemplateProvider;
use SilverStripe\Control\Cookie;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\SiteConfig\SiteConfig;

class ConsentViewTest extends SapphireTest
{
    protected $usesDatabase = true;

    protected function setUp(): void
    {
        parent::setUp();
        Cookie::force_expiry('SilverShopConsent');
    }

    public function testHasConsentGlobalWhenActive(): void
    {
        $config = SiteConfig::current_site_config();
        $config->CookieConsentActive = true;
        $config->write();

        ConsentState::save(['statistics']);

        $this->assertTrue(ConsentTemplateProvider::HasConsent('statistics'));
        $this->assertFalse(ConsentTemplateProvider::HasConsent('marketing'));
        $this->assertTrue(ConsentTemplateProvider::HasConsent('necessary'));
    }

    public function testHasConsentGlobalWhenInactiveGrantsAll(): void
    {
        $config = SiteConfig::current_site_config();
        $config->CookieConsentActive = false;
        $config->write();

        // With consent management off, gated regions still render.
        $this->assertTrue(ConsentTemplateProvider::HasConsent('marketing'));
        $this->assertFalse(ConsentTemplateProvider::NeedsPrompt());
    }

    public function testEmbedShortcodeRenders(): void
    {
        $parser = \SilverStripe\View\Parsers\ShortcodeParser::get('default');
        $html = ConsentEmbed::parse(
            ['src' => 'https://www.youtube.com/embed/xyz', 'category' => 'marketing', 'title' => 'Video'],
            null,
            $parser,
            'consent_embed'
        );

        $this->assertStringContainsString('data-consent-embed', $html);
        $this->assertStringContainsString('data-consent-category="marketing"', $html);
        $this->assertStringContainsString('data-embed-src="https://www.youtube.com/embed/xyz"', $html);
        $this->assertStringContainsString('consent-embed__placeholder', $html);
    }

    public function testEmbedShortcodeWithoutSrcIsEmpty(): void
    {
        $parser = \SilverStripe\View\Parsers\ShortcodeParser::get('default');
        $this->assertSame('', ConsentEmbed::parse([], null, $parser, 'consent_embed'));
    }
}
